<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductCommonValidator;
use Illuminate\Validation\Rule;

class ProductCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(ProductCommonValidator::getCommonRules($rec), [
      'name' => 'required|string|max:255|unique:products,name',
      'style_code' => [
        'required',
        'string',
        'max:50',
        Rule::unique('products')
          ->where('product_category_id', $rec['product_category_id'] ?? null)
          ->where('customer_id', $rec['customer_id'] ?? null)
          ->where('season_id', $rec['season_id'] ?? null),
      ],
    ]);
  }
}
