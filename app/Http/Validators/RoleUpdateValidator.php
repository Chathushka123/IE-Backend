<?php

namespace App\Http\Validators;

use App\Http\Validators\RoleCommonValidator;

class RoleUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], RoleCommonValidator::getCommonRules());
  }
}
