<?php

namespace App\Http\Validators;

class ProductCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'description' => 'required|string|max:255',
      'code' => 'nullable|string|max:50',
      'product_category_id' => 'required|integer|exists:product_categories,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
