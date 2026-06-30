<?php

namespace App\Http\Validators;

use App\Http\Validators\LinePlanCommonValidator;
use Illuminate\Validation\Rule;

class LinePlanUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge(LinePlanCommonValidator::getCommonRules($rec), [
      'sequence_no' => [
        'required',
        'integer',
        'min:1',
        Rule::unique('line_plans')->where('production_line_id', $rec['production_line_id'] ?? null)->ignore($keyIgnore),
      ],
    ]);
  }
}
