<?php

namespace App\Http\Validators;

class ProductCategoryCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'required|string|max:50',
      'product_family_id' => 'required|integer|exists:product_families,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
