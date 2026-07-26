<?php

namespace App\Http\Validators;

use App\Http\Validators\RegionCommonValidator;
use Illuminate\Validation\Rule;

class RegionUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge(RegionCommonValidator::getCommonRules(), [
      'name' => [
        'required',
        'string',
        'max:255',
        Rule::unique('regions')->where('country_id', $rec['country_id'] ?? null)->ignore($keyIgnore),
      ],
      'code' => ['nullable', 'string', 'max:50', Rule::unique('regions', 'code')->ignore($keyIgnore)],
    ]);
  }
}
