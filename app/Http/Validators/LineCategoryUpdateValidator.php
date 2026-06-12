<?php

namespace App\Http\Validators;

use App\Http\Validators\LineCategoryCommonValidator;

class LineCategoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], LineCategoryCommonValidator::getCommonRules());
  }
}
