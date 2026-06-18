<?php

namespace App\Http\Validators;

use App\Http\Validators\LineCategoryCommonValidator;

class LineCategoryCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(LineCategoryCommonValidator::getCommonRules(), [
      'description' => 'required|string|max:255|unique:line_categories,description',
    ]);
  }
}
