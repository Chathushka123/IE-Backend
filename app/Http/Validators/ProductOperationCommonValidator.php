<?php

namespace App\Http\Validators;

class ProductOperationCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'product_id' => 'required|integer|exists:products,id',
      'operation_id' => 'required|integer|exists:operations,id',
      'sequence_no' => 'required|integer|min:1',
      'smv' => 'nullable|numeric|min:0',
      'is_active' => 'nullable|boolean',
    ];
  }
}
