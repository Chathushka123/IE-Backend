<?php

namespace App\Http\Validators;

class StyleTaActionCommonValidator
{
  public static function getCommonRules(array $rec = [])
  {
    return [
      'style_id' => 'required|integer|exists:styles,id',
      'ta_template_action_id' => 'nullable|integer|exists:ta_template_actions,id',
      'phase' => 'required|string|max:50',
      'sequence' => 'nullable|integer|min:1',
      'name' => 'required|string|max:255',
      // Designation and employee are independent: no rule ties the employee to the designation.
      'designation_id' => 'nullable|integer|exists:designations,id',
      'employee_id' => 'nullable|integer|exists:employees,id',
      'process_time' => 'nullable|string|max:100',
      'planned_date' => 'nullable|date',
      'actual_date' => 'nullable|date',
      'remarks' => 'nullable|string|max:500',
    ];
  }
}
