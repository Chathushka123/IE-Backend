<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductCommonValidator;

class ProductCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(ProductCommonValidator::getCommonRules(), [
      'description' => 'required|string|max:255|unique:products,description',
      'code' => 'nullable|string|max:50|unique:products,code',
    ]);
  }
}
