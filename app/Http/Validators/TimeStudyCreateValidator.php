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
      'product_id' => 'nullable|integer|exists:products,id',
      'employee_id' => 'required|integer|exists:employees,id',
      'product_category_id' => 'nullable|integer|exists:product_categories,id',
      'machine_type_id' => 'nullable|integer|exists:machine_types,id',
      'smv' => 'nullable|numeric|min:0',

      'total_productive_ms' => 'required|integer|min:0',
      'total_down_time_ms' => 'required|integer|min:0',
      'total_hold_ms' => 'required|integer|min:0',
      'total_cycle_ms' => 'required|integer|min:0',
      'avg_cycle_ms' => 'nullable|integer|min:0',
      'fastest_cycle_ms' => 'nullable|integer|min:0',
      'slowest_cycle_ms' => 'nullable|integer|min:0',
      'efficiency_pct' => 'nullable|numeric|min:0',

      'skill_ids' => 'nullable|array',
      'skill_ids.*' => 'integer|exists:soft_skills,id',

      'laps' => 'required|array|min:1',
      'laps.*.lap_no' => 'required|integer|min:1',
      'laps.*.duration_ms' => 'required|integer|min:0',
      'laps.*.down_time_ms' => 'nullable|integer|min:0',
      'laps.*.net_ms' => 'required|integer|min:0',

      'downtimes' => 'nullable|array',
      'downtimes.*.lap_no' => 'nullable|integer|min:1',
      'downtimes.*.time_study_downtime_reason_id' => 'required|integer|exists:time_study_downtime_reasons,id',
      'downtimes.*.note' => 'nullable|string',
      'downtimes.*.duration_ms' => 'required|integer|min:0',
    ];
  }
}
