<?php

namespace App\Http\Validators;

class DesignationCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'required|string|max:50',
      'department_id' => 'nullable|integer|exists:departments,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
