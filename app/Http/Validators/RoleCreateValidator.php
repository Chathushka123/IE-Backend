<?php

namespace App\Http\Validators;

use App\Http\Validators\RoleCommonValidator;

class RoleCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], RoleCommonValidator::getCommonRules());
  }
}
