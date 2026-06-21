<?php

namespace App\Http\Validators;

use App\Http\Validators\OperationGradingCommonValidator;
use Illuminate\Validation\Rule;

class OperationGradingCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(OperationGradingCommonValidator::getCommonRules(), [
      'product_category_id' => [
        'required',
        'integer',
        'exists:product_categories,id',
        Rule::unique('operation_gradings')
          ->where('operation_id', $rec['operation_id'] ?? null)
          ->where('machine_type_id', $rec['machine_type_id'] ?? null),
      ],
      'code' => [
        'nullable',
        'string',
        'max:50',
        Rule::unique('operation_gradings'),
      ],
      'sequence_no' => [
        'nullable',
        'integer',
        'min:1',
        Rule::unique('operation_gradings')->where('product_category_id', $rec['product_category_id'] ?? null),
      ],
    ]);
  }
}
