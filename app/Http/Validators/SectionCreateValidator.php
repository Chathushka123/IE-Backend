<?php

namespace App\Http\Validators;

use App\Http\Validators\SectionCommonValidator;

class SectionCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(SectionCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:sections,name',
      'code' => 'required|string|max:50|unique:sections,code',
    ]);
  }
}
