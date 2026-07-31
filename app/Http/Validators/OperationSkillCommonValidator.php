<?php

namespace App\Http\Validators;

class OperationSkillCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'operation_id' => 'required|integer|exists:operations,id',
      'soft_skill_id' => 'required|integer|exists:soft_skills,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
