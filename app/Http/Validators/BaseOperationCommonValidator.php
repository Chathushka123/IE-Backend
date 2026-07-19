<?php

namespace App\Http\Validators;

class BaseOperationCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'nullable|string|max:50',
      'base_operation_category_id' => 'required|integer|exists:base_operation_categories,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
