<?php

namespace App\Http\Validators;

use App\Http\Validators\OperationCommonValidator;

class OperationCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(OperationCommonValidator::getCommonRules(), [
      'description' => 'required|string|max:255|unique:operations,description',
      'code' => 'nullable|string|max:50|unique:operations,code',
    ]);
  }
}
