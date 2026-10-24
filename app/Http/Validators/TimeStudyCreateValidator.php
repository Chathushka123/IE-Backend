<?php

namespace App\Http\Validators;

class TimeStudyCreateValidator
{
  public static function getCreateRules()
  {
    return [
      'factory_id' => 'nullable|integer|exists:factories,id',
      'study_date' => 'required|date',
      'time_study_type' => 'required|in:interview_training,production_floor',
      'operation_id' => 'required|integer|exists:operations,id',
      'style_id' => 'nullable|required_if:time_study_type,production_floor|integer|exists:styles,id',
      'employee_id' => 'required|integer|exists:employees,id',
      'conducted_by_id' => 'nullable|integer|exists:users,id',
      'product_category_id' => 'nullable|integer|exists:product_categories,id',
      'machine_type_id' => 'nullable|integer|exists:machine_types,id',
      // Deprecated client-side values: the server recomputes the SMV, the stats and the
      // efficiency from the laps and ignores whatever is sent for them (see TimeStudyRepository).
      'smv' => 'nullable|numeric|min:0',

      'total_productive_ms' => 'nullable|integer|min:0', // recomputed from total_cycle_ms - total_down_time_ms
      'total_down_time_ms' => 'required|integer|min:0',
      'total_hold_ms' => 'required|integer|min:0',
      'total_cycle_ms' => 'required|integer|min:0',
      'avg_cycle_ms' => 'nullable|integer|min:0',
      'fastest_cycle_ms' => 'nullable|integer|min:0',
      'slowest_cycle_ms' => 'nullable|integer|min:0',
      'efficiency_pct' => 'nullable|numeric|min:0',

      'template_version_id' => 'nullable|integer|exists:time_study_template_versions,id',
      'template_overridden' => 'nullable|boolean',
      'field_values' => 'nullable|array',

      'skill_ids' => 'nullable|array',
      'skill_ids.*' => 'integer|exists:base_skills,id',

      'laps' => 'required|array|min:1',
      'laps.*.lap_no' => 'required|integer|min:1',
      'laps.*.duration_ms' => 'required|integer|min:0',
      'laps.*.down_time_ms' => 'nullable|integer|min:0',
      'laps.*.net_ms' => 'required|integer|min:0',
      'laps.*.is_excluded' => 'nullable|boolean',
      'laps.*.outlier_kept' => 'nullable|boolean',

      'downtimes' => 'nullable|array',
      'downtimes.*.lap_no' => 'nullable|integer|min:1',
      'downtimes.*.time_study_downtime_reason_id' => 'required|integer|exists:time_study_downtime_reasons,id',
      'downtimes.*.note' => 'nullable|string',
      'downtimes.*.duration_ms' => 'required|integer|min:0',
    ];
  }
}
