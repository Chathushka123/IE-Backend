<?php

namespace App\Http\Validators;

use App\Http\Validators\EmployeeCategoryCommonValidator;

class EmployeeCategoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], EmployeeCategoryCommonValidator::getCommonRules());
  }
}
