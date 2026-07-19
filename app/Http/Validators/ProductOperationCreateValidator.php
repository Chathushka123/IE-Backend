<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductOperationCommonValidator;
use Illuminate\Validation\Rule;

class ProductOperationCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(ProductOperationCommonValidator::getCommonRules(), [
      'operation_id' => [
        'required',
        'integer',
        'exists:operations,id',
        Rule::unique('product_operations')->where('product_id', $rec['product_id'] ?? null),
      ],
      'sequence_no' => [
        'required',
        'integer',
        'min:1',
        Rule::unique('product_operations')->where('product_id', $rec['product_id'] ?? null),
      ],
    ]);
  }
}
