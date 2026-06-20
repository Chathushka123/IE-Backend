<?php

namespace App\Http\Validators;

class OperationSkillCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'operation_id' => 'required|integer|exists:operations,id',
      'skill_id' => 'required|integer|exists:skills,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
