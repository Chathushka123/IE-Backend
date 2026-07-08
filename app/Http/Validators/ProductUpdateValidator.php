<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductCommonValidator;
use Illuminate\Validation\Rule;

class ProductUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(ProductCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('products', 'description')->ignore($keyIgnore)],
      'style_code' => ['nullable', 'string', 'max:50', Rule::unique('products', 'style_code')->ignore($keyIgnore)],
    ]);
  }
}
