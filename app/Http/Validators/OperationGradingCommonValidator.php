<?php

namespace App\Http\Validators;

class OperationGradingCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'operation_id' => 'required|integer|exists:operations,id',
      'product_category_id' => 'required|integer|exists:product_categories,id',
      'machine_type_id' => 'required|integer|exists:machine_types,id',
      'description' => 'required|string|max:255',
      'code' => 'nullable|string|max:50',
      'grade_id' => 'required|integer|exists:operation_grades,id',
      'sequence_no' => 'nullable|integer|min:1',
      'smv' => 'nullable|numeric|min:0',
      'is_active' => 'nullable|boolean',
    ];
  }
}
