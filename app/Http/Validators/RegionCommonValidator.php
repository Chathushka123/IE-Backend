<?php

namespace App\Http\Validators;

class RegionCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'nullable|string|max:50',
      'country_id' => 'required|integer|exists:countries,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
