<?php

namespace App\Http\Repositories;

use App\TimeStudy;
use App\TimeStudySkill;
use App\TimeStudyLap;
use App\TimeStudyDowntime;
use App\TimeStudyTemplate;
use App\TimeStudyTemplateVersion;
use App\Support\TimeStudy\SmvResolver;
use App\Support\TimeStudy\StudyEvaluator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Exception;

use App\Http\Validators\TimeStudyCreateValidator;

class TimeStudyRepository
{
  // Header fields the client is allowed to set. Everything else on the row (SMV, cycle stats,
  // efficiency, template, status, quality) is decided here, never trusted from the request.
  private const CLIENT_HEADER_FIELDS = [
    'factory_id', 'study_date', 'time_study_type', 'operation_id', 'style_id', 'employee_id',
    'conducted_by_id', 'total_down_time_ms', 'total_hold_ms', 'total_cycle_ms',
  ];

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      TimeStudyCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }

    $skillIds = $rec['skill_ids'] ?? [];
    $laps = $rec['laps'] ?? [];
    $downtimes = $rec['downtimes'] ?? [];

    // The stored cycle stats/efficiency come from non-excluded laps, so at least one must count.
    $countedLaps = array_filter($laps, fn ($lap) => empty($lap['is_excluded']));
    if (count($countedLaps) === 0) {
      throw new \App\Exceptions\GeneralException('At least one lap must be included in the calculations.');
    }
    foreach ($laps as $lap) {
      if (($lap['down_time_ms'] ?? 0) > $lap['duration_ms']) {
        throw new \App\Exceptions\GeneralException('Lap ' . $lap['lap_no'] . ': down time cannot be longer than the lap itself.');
      }
    }

    $version = self::resolveTemplateVersion($rec['template_version_id'] ?? null);
    $definition = $version->definition;
    $purpose = $version->template->purpose;
    if ($purpose !== 'perf') {
      throw new \App\Exceptions\GeneralException('This template is for establishing an SMV. Record those studies as an SMV session.');
    }

    $operation = DB::table('operations')->where('id', $rec['operation_id'])->first();
    $smv = SmvResolver::resolve((int) $rec['operation_id'], $rec['style_id'] ?? null);

    $evaluation = StudyEvaluator::evaluate($definition, array_map(fn ($lap) => [
      'lap_no' => $lap['lap_no'],
      'duration_ms' => $lap['duration_ms'],
      'down_time_ms' => $lap['down_time_ms'] ?? 0,
      'excluded' => !empty($lap['is_excluded']),
      'outlier_kept' => !empty($lap['outlier_kept']),
    ], $laps), [
      'purpose' => $purpose,
      'values' => $rec['field_values'] ?? [],
      'smv' => $smv['smv'],
    ]);

    if (count($evaluation['blockers']) > 0) {
      $labels = implode(', ', array_column($evaluation['blockers'], 'label'));
      throw new \App\Exceptions\GeneralException('This study does not meet the template rules: ' . $labels . '.');
    }

    $headerData = array_intersect_key($rec, array_flip(self::CLIENT_HEADER_FIELDS));
    // No explicit conductor means the logged-in user conducted the study themselves.
    $headerData['conducted_by_id'] = $headerData['conducted_by_id'] ?? Auth::id();
    $stats = $evaluation['stats'];
    $headerData = array_merge($headerData, [
      // Snapshotted from the operation so analytics need not join through masters that may change.
      'product_category_id' => $operation->product_category_id,
      'machine_type_id' => $operation->machine_type_id,
      'smv' => $smv['smv'],
      'smv_source' => $smv['source'],
      'avg_cycle_ms' => $stats['avg_cycle_ms'],
      'fastest_cycle_ms' => $stats['fastest_cycle_ms'],
      'slowest_cycle_ms' => $stats['slowest_cycle_ms'],
      'efficiency_pct' => $stats['efficiency_pct'],
      'template_version_id' => $version->id,
      'template_overridden' => !empty($rec['template_overridden']),
      'purpose' => $purpose,
      'counts_in_matrix' => !empty($definition['counts_in_matrix']),
      'status' => $evaluation['status'],
      'quality_score' => $evaluation['quality_score'],
      'quality_breakdown' => $evaluation['quality_breakdown'],
      'rule_flags' => array_map(
        fn ($check) => ['id' => $check['id'], 'label' => $check['label']],
        $evaluation['warnings']
      ),
      'field_values' => $rec['field_values'] ?? null,
    ]);
    // Productive time is the stopwatch total minus its down time; the browser's own figure is not used.
    $headerData['total_productive_ms'] = max(0, ($rec['total_cycle_ms'] ?? 0) - ($rec['total_down_time_ms'] ?? 0));

    $model = self::persist($headerData, $evaluation['laps'], $skillIds, $downtimes);

    return $model;
  }

  /**
   * Saves one evaluated study: its header row, skills, laps (with the evaluator's decisions) and down time.
   * Shared by single studies and the per-operator runs of an SMV session.
   */
  public static function persist(array $headerData, array $evaluatedLaps, array $skillIds, array $downtimes): TimeStudy
  {
    try {
      $model = TimeStudy::create($headerData);

      foreach ($skillIds as $baseSkillId) {
        TimeStudySkill::create([
          'time_study_id' => $model->id,
          'base_skill_id' => $baseSkillId,
        ]);
      }

      foreach ($evaluatedLaps as $lap) {
        TimeStudyLap::create([
          'time_study_id' => $model->id,
          'lap_no' => $lap['lap_no'],
          'duration_ms' => $lap['duration_ms'],
          'down_time_ms' => $lap['down_time_ms'],
          'net_ms' => $lap['net_ms'],
          'is_excluded' => $lap['excluded'],
          'exclusion_source' => $lap['exclusion_source'],
          'outlier_flagged' => $lap['outlier_flagged'],
          'outlier_kept' => $lap['outlier_kept'],
        ]);
      }

      foreach ($downtimes as $downtime) {
        TimeStudyDowntime::create([
          'time_study_id' => $model->id,
          'lap_no' => $downtime['lap_no'] ?? null,
          'time_study_downtime_reason_id' => $downtime['time_study_downtime_reason_id'],
          'note' => $downtime['note'] ?? null,
          'duration_ms' => $downtime['duration_ms'],
        ]);
      }
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }

    return $model;
  }

  /**
   * Clients that do not send a template (everything before the template screens) run under the
   * Legacy template, which has every rule off and so behaves exactly as studies always did.
   */
  public static function resolveTemplateVersion(?int $versionId): TimeStudyTemplateVersion
  {
    if ($versionId === null) {
      return TimeStudyTemplate::legacyVersion()->load('template');
    }

    $version = TimeStudyTemplateVersion::with('template')->find($versionId);
    if ($version === null || $version->status !== 'published') {
      throw new \App\Exceptions\GeneralException('The selected template version is not published.');
    }
    if ($version->template->archived_at !== null) {
      throw new \App\Exceptions\GeneralException('The selected template has been archived.');
    }

    return $version;
  }

  public static function deleteRecs(array $recs)
  {
    TimeStudy::destroy($recs);
  }
}
