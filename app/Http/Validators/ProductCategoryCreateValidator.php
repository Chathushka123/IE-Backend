<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductCategoryCommonValidator;

class ProductCategoryCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(ProductCategoryCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:product_categories,name',
      'code' => 'required|string|max:50|unique:product_categories,code',
    ]);
  }
}
