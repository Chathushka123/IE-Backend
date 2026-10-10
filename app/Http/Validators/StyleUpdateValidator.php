<?php

namespace App\Http\Validators;

use App\Http\Validators\StyleCommonValidator;
use Illuminate\Validation\Rule;

class StyleUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge(StyleCommonValidator::getCommonRules($rec), [
      'name' => ['required', 'string', 'max:255', Rule::unique('styles', 'name')->ignore($keyIgnore)],
      'style_code' => [
        'required',
        'string',
        'max:50',
        Rule::unique('styles')
          ->where('product_category_id', $rec['product_category_id'] ?? null)
          ->where('customer_id', $rec['customer_id'] ?? null)
          ->where('season_id', $rec['season_id'] ?? null)
          ->ignore($keyIgnore),
      ],
    ]);
  }
}
