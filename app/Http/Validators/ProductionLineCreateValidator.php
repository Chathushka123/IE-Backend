<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductionLineCommonValidator;

class ProductionLineCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], ProductionLineCommonValidator::getCommonRules());
  }
}
