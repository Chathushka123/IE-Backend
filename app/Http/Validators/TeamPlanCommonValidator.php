<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class TeamPlanCommonValidator
{
  public static function getCommonRules(array $rec = [])
  {
    $isChangeover = !empty($rec['is_changeover']);

    return [
      'team_id' => 'required|integer|exists:teams,id',
      'product_id' => [Rule::requiredIf(!$isChangeover), 'nullable', 'integer', 'exists:products,id'],
      'sequence_no' => 'required|integer|min:1',
      'planned_quantity' => [Rule::requiredIf(!$isChangeover), 'nullable', 'integer', 'min:1'],
      'planned_start_date' => 'nullable|date',
      'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
      'actual_start_date' => 'nullable|date',
      'actual_end_date' => 'nullable|date|after_or_equal:actual_start_date',
      'status' => 'nullable|in:planned,in_progress,completed,on_hold,cancelled',
      'notes' => 'nullable|string|max:2000',
      'is_changeover' => 'nullable|boolean',
      'is_active' => 'nullable|boolean',
    ];
  }
}
