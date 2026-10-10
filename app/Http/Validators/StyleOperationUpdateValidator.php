<?php

namespace App\Http\Validators;

use App\Http\Validators\StyleOperationCommonValidator;
use Illuminate\Validation\Rule;

class StyleOperationUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge(StyleOperationCommonValidator::getCommonRules(), [
      'operation_id' => [
        'required',
        'integer',
        'exists:operations,id',
        Rule::unique('style_operations')->where('style_id', $rec['style_id'] ?? null)->ignore($keyIgnore),
      ],
      'sequence_no' => [
        'required',
        'integer',
        'min:1',
        Rule::unique('style_operations')->where('style_id', $rec['style_id'] ?? null)->ignore($keyIgnore),
      ],
    ]);
  }
}
