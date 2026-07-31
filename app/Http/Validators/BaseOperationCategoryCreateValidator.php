<?php

namespace App\Http\Validators;

use App\Http\Validators\BaseOperationCategoryCommonValidator;

class BaseOperationCategoryCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(BaseOperationCategoryCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:base_operation_categories,name',
      'code' => 'nullable|string|max:50|unique:base_operation_categories,code',
    ]);
  }
}
