<?php

namespace App\Http\Validators;

use App\Http\Validators\BaseOperationCommonValidator;

class BaseOperationCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(BaseOperationCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:base_operations,name',
      'code' => 'nullable|string|max:50|unique:base_operations,code',
    ]);
  }
}
