<?php

namespace App\Http\Validators;

use App\Http\Validators\FactoryCommonValidator;

class FactoryCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(FactoryCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:factories,name',
      'code' => 'nullable|string|max:50|unique:factories,code',
    ]);
  }
}
