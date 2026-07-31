<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductGroupCommonValidator;

class ProductGroupCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(ProductGroupCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:product_groups,name',
      'code' => 'required|string|max:50|unique:product_groups,code',
    ]);
  }
}
