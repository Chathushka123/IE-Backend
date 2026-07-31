<?php

namespace App\Http\Validators;

class CustomerCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'description' => 'required|string|max:255',
      'code' => 'required|string|max:50',
      'is_active' => 'nullable|boolean',
    ];
  }
}
