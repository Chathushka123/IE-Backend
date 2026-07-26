<?php

namespace App\Http\Validators;

use App\Http\Validators\RegionCommonValidator;
use Illuminate\Validation\Rule;

class RegionCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(RegionCommonValidator::getCommonRules(), [
      'name' => [
        'required',
        'string',
        'max:255',
        Rule::unique('regions')->where('country_id', $rec['country_id'] ?? null),
      ],
      'code' => 'nullable|string|max:50|unique:regions,code',
    ]);
  }
}
