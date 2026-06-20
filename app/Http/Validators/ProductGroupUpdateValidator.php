<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductGroupCommonValidator;
use Illuminate\Validation\Rule;

class ProductGroupUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(ProductGroupCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('product_groups', 'description')->ignore($keyIgnore)],
    ]);
  }
}
