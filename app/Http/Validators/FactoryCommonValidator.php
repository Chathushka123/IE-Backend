<?php

namespace App\Http\Validators;

class FactoryCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'description' => 'required|string|max:255',
      'code' => 'nullable|string|max:50',
      'is_active' => 'nullable|boolean',
    ];
  }
}
