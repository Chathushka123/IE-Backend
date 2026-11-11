<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductFamilyCommonValidator;
use Illuminate\Validation\Rule;

class ProductFamilyUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(ProductFamilyCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('product_families', 'name')->ignore($keyIgnore)],
      'code' => ['required', 'string', 'max:50', Rule::unique('product_families', 'code')->ignore($keyIgnore)],
    ]);
  }
}
