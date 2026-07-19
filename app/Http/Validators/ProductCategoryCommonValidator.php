<?php

namespace App\Http\Validators;

class ProductCategoryCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'nullable|string|max:50',
      'product_group_id' => 'required|integer|exists:product_groups,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
