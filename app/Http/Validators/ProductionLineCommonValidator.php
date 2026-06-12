<?php

namespace App\Http\Validators;

class ProductionLineCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'description' => 'required|string|max:255',
      'code' => 'nullable|string|max:50',
      'category_id' => 'required|integer|exists:line_categories,id',
      'department_id' => 'required|integer|exists:departments,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
