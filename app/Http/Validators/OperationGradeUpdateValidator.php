<?php

namespace App\Http\Validators;

use App\Http\Validators\OperationGradeCommonValidator;
use Illuminate\Validation\Rule;

class OperationGradeUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(OperationGradeCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('operation_grades', 'name')->ignore($keyIgnore)],
      'code' => ['nullable', 'string', 'max:50', Rule::unique('operation_grades', 'code')->ignore($keyIgnore)],
    ]);
  }
}
