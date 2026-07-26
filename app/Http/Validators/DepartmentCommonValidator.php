<?php

namespace App\Http\Validators;

class DepartmentCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'required|string|max:50',
      'is_active' => 'nullable|boolean',
    ];
  }
}
