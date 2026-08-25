<?php

namespace App\Http\Repositories;

use App\Employee;
use App\TeamPlan;
use App\Product;
use App\Team;
use App\ProductOperation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Exception;

use App\Http\Validators\TeamPlanCreateValidator;
use App\Http\Validators\TeamPlanUpdateValidator;

class TeamPlanRepository
{
  private static function assertSingleInProgressPerLine(array $rec, $ignoreId = null)
  {
    if (($rec['status'] ?? null) !== 'in_progress') {
      return;
    }

    $query = TeamPlan::where('team_id', $rec['team_id'])
      ->where('status', 'in_progress');
    if ($ignoreId) {
      $query->where('id', '!=', $ignoreId);
    }

    if ($query->exists()) {
      throw new \App\Exceptions\GeneralException(
        'This team already has another style in progress. Complete or put it on hold before starting a new one.'
      );
    }
  }

  /**
   * A team can only run one product/changeover at a time, so a new or edited
   * plan's [planned_start_date, planned_end_date] (inclusive on both ends —
   * an end date of 09-05 and the next plan's start date of 09-06 are back to
   * back, not overlapping) must not intersect any other non-cancelled plan
   * already on that team.
   */
  private static function assertNoOverlap(array $rec, $ignoreId = null)
  {
    $start = $rec['planned_start_date'] ?? null;
    $end = $rec['planned_end_date'] ?? null;
    if (!$start || !$end) {
      return;
    }

    $query = TeamPlan::where('team_id', $rec['team_id'])
      ->where('status', '!=', 'cancelled')
      ->where('planned_start_date', '<=', $end)
      ->where('planned_end_date', '>=', $start);
    if ($ignoreId) {
      $query->where('id', '!=', $ignoreId);
    }

    $conflict = $query->first();
    if ($conflict) {
      $label = $conflict->is_changeover
        ? 'a style changeover'
        : 'product #' . $conflict->product_id;
      $conflictStart = $conflict->planned_start_date->format('Y-m-d');
      $conflictEnd = $conflict->planned_end_date->format('Y-m-d');
      throw new \App\Exceptions\GeneralException(
        "This team is already scheduled for {$label} from {$conflictStart} to {$conflictEnd}."
      );
    }
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      TeamPlanCreateValidator::getCreateRules($rec),
      [
        'sequence_no.unique' => 'This sequence number is already used by another style queued on this line.',
      ]
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    self::assertSingleInProgressPerLine($rec);
    self::assertNoOverlap($rec);
    try {
      $model = TeamPlan::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = TeamPlan::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      TeamPlanUpdateValidator::getUpdateRules($model_id, $rec),
      [
        'sequence_no.unique' => 'This sequence number is already used by another style queued on this line.',
      ]
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    self::assertSingleInProgressPerLine($rec, $model_id);
    self::assertNoOverlap($rec, $model_id);
    try {
      $model->update($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function createMultipleRecs($master_id, array $recs)
  {
    $ret = [];
    foreach ($recs as $rec) {
      $parent_key = array_search("!PARENT_KEY!", $rec);
      if ($parent_key) {
        $rec[$parent_key] = $master_id;
      }
      $ret[] = self::createRec($rec);
    }

    return $ret;
  }

  public static function updateMultipleRecs($master_id, array $recs)
  {
    $ret = [];
    foreach ($recs as $index => $body) {
      foreach ($body as $child_id => $rec) {
        $parent_key = array_search("!PARENT_KEY!", $rec);
        if ($parent_key) {
          $rec[$parent_key] = $master_id;
        }
        $ret[] = self::updateRec($child_id, $rec);
      }
    }

    return $ret;
  }

  public static function deleteRecs(array $recs)
  {
    TeamPlan::destroy($recs);
  }

  public static function resequence(int $teamId, array $ids)
  {
    $records = TeamPlan::whereIn('id', $ids)
      ->where('team_id', $teamId)
      ->get()
      ->keyBy('id');

    if ($records->count() !== count($ids)) {
      throw new \App\Exceptions\GeneralException(
        'One or more IDs do not belong to the given team.'
      );
    }

    // Null out all sequence numbers first to avoid unique constraint conflicts
    TeamPlan::whereIn('id', $ids)->update(['sequence_no' => null]);

    // Use query builder directly — Eloquent's dirty-check would skip save()
    // for any record whose new sequence_no equals its original value
    foreach ($ids as $index => $id) {
      TeamPlan::where('id', $id)->update(['sequence_no' => $index + 1]);
    }

    return TeamPlan::whereIn('id', $ids)->orderBy('sequence_no')->get();
  }

  /**
   * Hourly capacity = (active operators on the line x 60 min x target efficiency%) / total SMV of the product.
   * Treats the line as continuously available for the needed duration — it does not model
   * shift/working-hours calendars. Returned as a suggestion only; nothing here is persisted.
   */
  public static function suggestSchedule(int $teamId, int $productId, int $plannedQuantity, ?string $startDate = null)
  {
    $line = Team::findOrFail($teamId);
    Product::findOrFail($productId);

    $operatorCount = Employee::where('team_id', $teamId)
      ->where('employee_status', 'Active')
      ->count();

    $totalSmv = (float) ProductOperation::where('product_id', $productId)
      ->where('is_active', true)
      ->sum('smv');

    $efficiencyPct = $line->target_efficiency_pct;

    if ($operatorCount <= 0) {
      throw new \App\Exceptions\GeneralException('Cannot suggest a schedule: this team has no active operators assigned.');
    }
    if ($totalSmv <= 0) {
      throw new \App\Exceptions\GeneralException('Cannot suggest a schedule: the selected product has no SMV defined in its operation gradings.');
    }
    if (empty($efficiencyPct)) {
      throw new \App\Exceptions\GeneralException('Cannot suggest a schedule: set a target efficiency % on this team first.');
    }

    $hourlyCapacity = (int) floor(($operatorCount * 60 * ($efficiencyPct / 100)) / $totalSmv);
    if ($hourlyCapacity <= 0) {
      throw new \App\Exceptions\GeneralException('Computed hourly capacity is zero — check the operator count, efficiency and SMV inputs.');
    }

    $start = $startDate ? Carbon::parse($startDate)->startOfDay() : self::nextAvailableStartDate($teamId);
    $hoursNeeded = (int) ceil($plannedQuantity / $hourlyCapacity);

    $workingMinutesPerDay = $line->working_minutes_per_day;
    if (empty($workingMinutesPerDay)) {
      throw new \App\Exceptions\GeneralException('Cannot suggest a schedule: set working minutes per day on this team first.');
    }
    $daysNeeded = (int) ceil(($hoursNeeded * 60) / $workingMinutesPerDay);
    // Inclusive-range convention: a 1-day job starts and ends on the same calendar date.
    $end = $start->copy()->addDays($daysNeeded - 1);

    return [
      'operator_count' => $operatorCount,
      'total_smv' => $totalSmv,
      'target_efficiency_pct' => (float) $efficiencyPct,
      'hourly_capacity' => $hourlyCapacity,
      'hours_needed' => $hoursNeeded,
      'days_needed' => $daysNeeded,
      'suggested_start_date' => $start->toDateString(),
      'suggested_end_date' => $end->toDateString(),
    ];
  }

  /**
   * The day after the last currently-scheduled plan's end date on this team —
   * under the inclusive-range convention, that end date itself is occupied.
   */
  private static function nextAvailableStartDate(int $teamId)
  {
    $lastEnd = TeamPlan::where('team_id', $teamId)
      ->whereIn('status', ['planned', 'in_progress'])
      ->max('planned_end_date');

    return $lastEnd ? Carbon::parse($lastEnd)->startOfDay()->addDay() : Carbon::now()->startOfDay();
  }
}
