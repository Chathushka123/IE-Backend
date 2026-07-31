<?php

namespace App\Http\Validators;

use App\Http\Validators\SeasonCommonValidator;
use Illuminate\Validation\Rule;

class SeasonCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(SeasonCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255',
      'code' => [
        'required',
        'string',
        'max:50',
        Rule::unique('seasons')->where('customer_id', $rec['customer_id'] ?? null),
      ],
    ]);
  }
}
