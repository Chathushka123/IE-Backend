<?php

namespace App\Http\Validators;

use App\Http\Validators\OperationGradingSkillCommonValidator;
use Illuminate\Validation\Rule;

class OperationGradingSkillCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(OperationGradingSkillCommonValidator::getCommonRules(), [
      'skill_id' => [
        'required',
        'integer',
        'exists:skills,id',
        Rule::unique('operation_grading_skill')->where('operation_grading_id', $rec['operation_grading_id'] ?? null),
      ],
    ]);
  }
}
