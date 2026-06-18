<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductionLineCommonValidator;

class ProductionLineCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(ProductionLineCommonValidator::getCommonRules(), [
      'description' => 'required|string|max:255|unique:production_lines,description',
    ]);
  }
}
