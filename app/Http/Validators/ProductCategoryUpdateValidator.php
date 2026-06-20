<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductCategoryCommonValidator;
use Illuminate\Validation\Rule;

class ProductCategoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(ProductCategoryCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('product_categories', 'description')->ignore($keyIgnore)],
    ]);
  }
}
