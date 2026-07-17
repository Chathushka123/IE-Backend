<?php

namespace App\Http\Validators;

class ProductCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'description' => 'required|string|max:255',
      'style_code' => 'nullable|string|max:50',
      'style_description' => 'nullable|string|max:255',
      'product_category_id' => 'required|integer|exists:product_categories,id',
      'customer_id' => 'nullable|integer|exists:customers,id',
      'season' => 'nullable|string|max:50',
      'colors' => 'nullable|array',
      'colors.*' => 'string|max:50',
      'sizes' => 'nullable|array',
      'sizes.*' => 'string|max:20',
      'customer_requested_delivery_date' => 'nullable|date',
      'planned_efficiency_pct' => 'nullable|numeric|min:0|max:100',
      'is_active' => 'nullable|boolean',
      'factory_ids' => 'nullable|array',
      'factory_ids.*' => 'integer|exists:factories,id',
    ];
  }
}
