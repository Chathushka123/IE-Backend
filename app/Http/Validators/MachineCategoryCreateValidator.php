<?php

namespace App\Http\Validators;

use App\Http\Validators\MachineCategoryCommonValidator;

class MachineCategoryCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(MachineCategoryCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:machine_categories,name',
      'code' => 'nullable|string|max:50|unique:machine_categories,code',
    ]);
  }
}
