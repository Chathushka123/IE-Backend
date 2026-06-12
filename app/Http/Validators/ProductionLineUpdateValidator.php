<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductionLineCommonValidator;

class ProductionLineUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], ProductionLineCommonValidator::getCommonRules());
  }
}
