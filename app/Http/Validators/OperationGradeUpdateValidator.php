<?php

namespace App\Http\Validators;

use App\Http\Validators\OperationGradeCommonValidator;
use Illuminate\Validation\Rule;

class OperationGradeUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(OperationGradeCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('operation_grades', 'description')->ignore($keyIgnore)],
    ]);
  }
}
