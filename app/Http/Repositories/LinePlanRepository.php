<?php

namespace App\Http\Repositories;

use App\Employee;
use App\LinePlan;
use App\Product;
use App\ProductionLine;
use App\ProductOperationGrading;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Exception;

use App\Http\Validators\LinePlanCreateValidator;
use App\Http\Validators\LinePlanUpdateValidator;

class LinePlanRepository
{
  private static function assertSingleInProgressPerLine(array $rec, $ignoreId = null)
  {
    if (($rec['status'] ?? null) !== 'in_progress') {
      return;
    }

    $query = LinePlan::where('production_line_id', $rec['production_line_id'])
      ->where('status', 'in_progress');
    if ($ignoreId) {
      $query->where('id', '!=', $ignoreId);
    }

    if ($query->exists()) {
      throw new \App\Exceptions\GeneralException(
        'This line already has another style in progress. Complete or put it on hold before starting a new one.'
      );
    }
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      LinePlanCreateValidator::getCreateRules($rec),
      [
        'sequence_no.unique' => 'This sequence number is already used by another style queued on this line.',
      ]
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    self::assertSingleInProgressPerLine($rec);
    try {
      $model = LinePlan::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = LinePlan::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      LinePlanUpdateValidator::getUpdateRules($model_id, $rec),
      [
        'sequence_no.unique' => 'This sequence number is already used by another style queued on this line.',
      ]
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    self::assertSingleInProgressPerLine($rec, $model_id);
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
    LinePlan::destroy($recs);
  }

  public static function resequence(int $productionLineId, array $ids)
  {
    $records = LinePlan::whereIn('id', $ids)
      ->where('production_line_id', $productionLineId)
      ->get()
      ->keyBy('id');

    if ($records->count() !== count($ids)) {
      throw new \App\Exceptions\GeneralException(
        'One or more IDs do not belong to the given production line.'
      );
    }

    // Null out all sequence numbers first to avoid unique constraint conflicts
    LinePlan::whereIn('id', $ids)->update(['sequence_no' => null]);

    // Use query builder directly — Eloquent's dirty-check would skip save()
    // for any record whose new sequence_no equals its original value
    foreach ($ids as $index => $id) {
      LinePlan::where('id', $id)->update(['sequence_no' => $index + 1]);
    }

    return LinePlan::whereIn('id', $ids)->orderBy('sequence_no')->get();
  }

  /**
   * Hourly capacity = (active operators on the line x 60 min x target efficiency%) / total SMV of the product.
   * Treats the line as continuously available for the needed duration — it does not model
   * shift/working-hours calendars. Returned as a suggestion only; nothing here is persisted.
   */
  public static function suggestSchedule(int $productionLineId, int $productId, int $plannedQuantity, ?string $startDate = null)
  {
    $line = ProductionLine::findOrFail($productionLineId);
    Product::findOrFail($productId);

    $operatorCount = Employee::where('production_line_id', $productionLineId)
      ->where('employee_status', 'Active')
      ->count();

    $totalSmv = (float) ProductOperationGrading::where('product_id', $productId)
      ->where('is_active', true)
      ->sum('smv');

    $efficiencyPct = $line->target_efficiency_pct;

    if ($operatorCount <= 0) {
      throw new \App\Exceptions\GeneralException('Cannot suggest a schedule: this line has no active operators assigned.');
    }
    if ($totalSmv <= 0) {
      throw new \App\Exceptions\GeneralException('Cannot suggest a schedule: the selected product has no SMV defined in its operation gradings.');
    }
    if (empty($efficiencyPct)) {
      throw new \App\Exceptions\GeneralException('Cannot suggest a schedule: set a target efficiency % on this production line first.');
    }

    $hourlyCapacity = (int) floor(($operatorCount * 60 * ($efficiencyPct / 100)) / $totalSmv);
    if ($hourlyCapacity <= 0) {
      throw new \App\Exceptions\GeneralException('Computed hourly capacity is zero — check the operator count, efficiency and SMV inputs.');
    }

    $start = $startDate ? Carbon::parse($startDate) : self::nextAvailableStartDateTime($productionLineId);
    $hoursNeeded = (int) ceil($plannedQuantity / $hourlyCapacity);
    $end = $start->copy()->addHours($hoursNeeded);

    return [
      'operator_count' => $operatorCount,
      'total_smv' => $totalSmv,
      'target_efficiency_pct' => (float) $efficiencyPct,
      'hourly_capacity' => $hourlyCapacity,
      'hours_needed' => $hoursNeeded,
      'suggested_start_date' => $start->toDateTimeString(),
      'suggested_end_date' => $end->toDateTimeString(),
    ];
  }

  private static function nextAvailableStartDateTime(int $productionLineId)
  {
    $lastEnd = LinePlan::where('production_line_id', $productionLineId)
      ->whereIn('status', ['planned', 'in_progress'])
      ->max('planned_end_date');

    return $lastEnd ? Carbon::parse($lastEnd) : Carbon::now();
  }
}
