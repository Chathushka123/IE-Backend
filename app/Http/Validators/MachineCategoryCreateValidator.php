<?php

namespace App\Http\Validators;

use App\Http\Validators\MachineCategoryCommonValidator;

class MachineCategoryCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(MachineCategoryCommonValidator::getCommonRules(), [
      'description' => 'required|string|max:255|unique:machine_categories,description',
      'code' => 'nullable|string|max:50|unique:machine_categories,code',
    ]);
  }
}
