<?php

namespace App\Http\Validators;

use App\Http\Validators\DepartmentCommonValidator;

class DepartmentUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], DepartmentCommonValidator::getCommonRules());
  }
}
