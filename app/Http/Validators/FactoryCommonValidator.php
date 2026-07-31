<?php

namespace App\Http\Validators;

class FactoryCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'required|string|max:50',
      'country_id' => 'required|integer|exists:countries,id',
      'region_id' => 'required|integer|exists:regions,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
