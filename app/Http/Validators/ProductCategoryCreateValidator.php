<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductCategoryCommonValidator;

class ProductCategoryCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(ProductCategoryCommonValidator::getCommonRules(), [
      'description' => 'required|string|max:255|unique:product_categories,description',
    ]);
  }
}
