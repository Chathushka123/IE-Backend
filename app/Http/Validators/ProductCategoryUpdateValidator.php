<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductCategoryCommonValidator;
use Illuminate\Validation\Rule;

class ProductCategoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(ProductCategoryCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('product_categories', 'name')->ignore($keyIgnore)],
      'code' => ['required', 'string', 'max:50', Rule::unique('product_categories', 'code')->ignore($keyIgnore)],
    ]);
  }
}
