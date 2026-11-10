<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductFamilyCommonValidator;

class ProductFamilyCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(ProductFamilyCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:product_families,name',
      'code' => 'required|string|max:50|unique:product_families,code',
    ]);
  }
}
