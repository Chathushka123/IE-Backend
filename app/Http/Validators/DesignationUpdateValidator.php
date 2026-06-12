<?php

namespace App\Http\Validators;

use App\Http\Validators\DesignationCommonValidator;

class DesignationUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], DesignationCommonValidator::getCommonRules());
  }
}
