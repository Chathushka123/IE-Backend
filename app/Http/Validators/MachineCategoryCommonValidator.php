<?php

namespace App\Http\Validators;

class MachineCategoryCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'nullable|string|max:50',
      'is_active' => 'nullable|boolean',
    ];
  }
}
