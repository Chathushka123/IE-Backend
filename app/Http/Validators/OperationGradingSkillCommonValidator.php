<?php

namespace App\Http\Validators;

class OperationGradingSkillCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'operation_grading_id' => 'required|integer|exists:operation_gradings,id',
      'skill_id' => 'required|integer|exists:skills,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
