<?php

namespace App\Http\Validators;

class SeasonCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'required|string|max:50',
      'customer_id' => 'required|integer|exists:customers,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
