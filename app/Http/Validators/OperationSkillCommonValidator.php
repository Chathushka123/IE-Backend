<?php

namespace App\Http\Validators;

class OperationSkillCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'operation_id' => 'required|integer|exists:operations,id',
      'base_skill_id' => 'required|integer|exists:base_skills,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
