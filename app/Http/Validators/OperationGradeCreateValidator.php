<?php

namespace App\Http\Validators;

use App\Http\Validators\OperationGradeCommonValidator;

class OperationGradeCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(OperationGradeCommonValidator::getCommonRules(), [
      'description' => 'required|string|max:255|unique:operation_grades,description',
    ]);
  }
}
