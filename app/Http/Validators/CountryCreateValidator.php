<?php

namespace App\Http\Validators;

use App\Http\Validators\CountryCommonValidator;

class CountryCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(CountryCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:countries,name',
      'code' => 'required|string|max:50|unique:countries,code',
    ]);
  }
}
