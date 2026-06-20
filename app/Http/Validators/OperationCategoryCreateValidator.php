<?php

namespace App\Http\Validators;

use App\Http\Validators\OperationCategoryCommonValidator;

class OperationCategoryCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(OperationCategoryCommonValidator::getCommonRules(), [
      'description' => 'required|string|max:255|unique:operation_categories,description',
    ]);
  }
}
