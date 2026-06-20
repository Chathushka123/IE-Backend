<?php

namespace App\Http\Validators;

class OperationCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'description' => 'required|string|max:255',
      'code' => 'nullable|string|max:50',
      'operation_category_id' => 'required|integer|exists:operation_categories,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
