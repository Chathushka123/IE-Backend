<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductOperationGradingCommonValidator;
use Illuminate\Validation\Rule;

class ProductOperationGradingUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge(ProductOperationGradingCommonValidator::getCommonRules(), [
      'operation_grading_id' => [
        'required',
        'integer',
        'exists:operation_gradings,id',
        Rule::unique('product_operation_gradings')->where('product_id', $rec['product_id'] ?? null)->ignore($keyIgnore),
      ],
      'sequence_no' => [
        'required',
        'integer',
        'min:1',
        Rule::unique('product_operation_gradings')->where('product_id', $rec['product_id'] ?? null)->ignore($keyIgnore),
      ],
    ]);
  }
}
