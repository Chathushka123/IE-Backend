<?php

namespace App\Http\Validators;

class ProductOperationGradingCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'product_id' => 'required|integer|exists:products,id',
      'operation_grading_id' => 'required|integer|exists:operation_gradings,id',
      'sequence_no' => 'required|integer|min:1',
      'smv' => 'nullable|numeric|min:0',
      'is_active' => 'nullable|boolean',
    ];
  }
}
