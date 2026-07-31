<?php

namespace App\Http\Validators;

class DestinationCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'required|string|max:50',
      'customer_ids' => 'nullable|array',
      'customer_ids.*' => 'integer|exists:customers,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
