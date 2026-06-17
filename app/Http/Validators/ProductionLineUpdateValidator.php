<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductionLineCommonValidator;
use Illuminate\Validation\Rule;

class ProductionLineUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(ProductionLineCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('production_lines', 'description')->ignore($keyIgnore)],
    ]);
  }
}
