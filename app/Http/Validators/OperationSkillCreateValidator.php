<?php

namespace App\Http\Validators;

use App\Http\Validators\OperationSkillCommonValidator;
use Illuminate\Validation\Rule;

class OperationSkillCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(OperationSkillCommonValidator::getCommonRules(), [
      'soft_skill_id' => [
        'required',
        'integer',
        'exists:soft_skills,id',
        Rule::unique('operation_skill')->where('operation_id', $rec['operation_id'] ?? null),
      ],
    ]);
  }
}
