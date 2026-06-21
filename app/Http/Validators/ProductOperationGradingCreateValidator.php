<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductOperationGradingCommonValidator;
use Illuminate\Validation\Rule;

class ProductOperationGradingCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(ProductOperationGradingCommonValidator::getCommonRules(), [
      'operation_grading_id' => [
        'required',
        'integer',
        'exists:operation_gradings,id',
        Rule::unique('product_operation_gradings')->where('product_id', $rec['product_id'] ?? null),
      ],
      'sequence_no' => [
        'required',
        'integer',
        'min:1',
        Rule::unique('product_operation_gradings')->where('product_id', $rec['product_id'] ?? null),
      ],
    ]);
  }
}
