<?php

namespace App\Http\Validators;

use App\Http\Validators\OperationCommonValidator;
use Illuminate\Validation\Rule;

class OperationUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge(OperationCommonValidator::getCommonRules(), [
      'product_category_id' => [
        'required',
        'integer',
        'exists:product_categories,id',
        Rule::unique('operations')
          ->where('base_operation_id', $rec['base_operation_id'] ?? null)
          ->where('machine_type_id', $rec['machine_type_id'] ?? null)
          ->ignore($keyIgnore),
      ],
      'code' => [
        'required',
        'string',
        'max:50',
        Rule::unique('operations')->ignore($keyIgnore),
      ],
      'sequence_no' => [
        'nullable',
        'integer',
        'min:1',
        Rule::unique('operations')->where('product_category_id', $rec['product_category_id'] ?? null)->ignore($keyIgnore),
      ],
    ]);
  }
}
