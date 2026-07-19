<?php

namespace App\Http\Validators;

use App\Http\Validators\OperationGradeCommonValidator;

class OperationGradeCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(OperationGradeCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:operation_grades,name',
      'code' => 'nullable|string|max:50|unique:operation_grades,code',
    ]);
  }
}
