<?php

namespace App\Http\Validators;

class MachineTypeCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'required|string|max:50',
      'machine_category_id' => 'required|integer|exists:machine_categories,id',
      'standard_rpm' => 'nullable|integer|min:1|max:100000',
      'is_active' => 'nullable|boolean',
    ];
  }
}
