<?php

namespace App\Http\Validators;

use App\Http\Validators\CustomerCommonValidator;

class CustomerCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(CustomerCommonValidator::getCommonRules(), [
      'description' => 'required|string|max:255|unique:customers,description',
      'code' => 'nullable|string|max:50|unique:customers,code',
    ]);
  }
}
