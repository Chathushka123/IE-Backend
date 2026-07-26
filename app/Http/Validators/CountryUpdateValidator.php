<?php

namespace App\Http\Validators;

use App\Http\Validators\CountryCommonValidator;
use Illuminate\Validation\Rule;

class CountryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(CountryCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('countries', 'name')->ignore($keyIgnore)],
      'code' => ['required', 'string', 'max:50', Rule::unique('countries', 'code')->ignore($keyIgnore)],
    ]);
  }
}
