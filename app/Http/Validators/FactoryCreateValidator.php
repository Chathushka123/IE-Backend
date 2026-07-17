<?php

namespace App\Http\Validators;

use App\Http\Validators\FactoryCommonValidator;

class FactoryCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(FactoryCommonValidator::getCommonRules(), [
      'description' => 'required|string|max:255|unique:factories,description',
      'code' => 'nullable|string|max:50|unique:factories,code',
    ]);
  }
}
