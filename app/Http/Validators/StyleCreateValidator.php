<?php

namespace App\Http\Validators;

use App\Http\Validators\StyleCommonValidator;
use Illuminate\Validation\Rule;

class StyleCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(StyleCommonValidator::getCommonRules($rec), [
      'name' => 'required|string|max:255|unique:styles,name',
      'style_code' => [
        'required',
        'string',
        'max:50',
        Rule::unique('styles')
          ->where('product_category_id', $rec['product_category_id'] ?? null)
          ->where('customer_id', $rec['customer_id'] ?? null)
          ->where('season_id', $rec['season_id'] ?? null),
      ],
    ]);
  }
}
