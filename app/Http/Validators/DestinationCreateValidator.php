<?php

namespace App\Http\Validators;

use App\Http\Validators\DestinationCommonValidator;

class DestinationCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(DestinationCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:destinations,name',
      'code' => 'required|string|max:50|unique:destinations,code',
    ]);
  }
}
