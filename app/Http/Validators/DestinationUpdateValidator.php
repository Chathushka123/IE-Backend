<?php

namespace App\Http\Validators;

use App\Http\Validators\DestinationCommonValidator;
use Illuminate\Validation\Rule;

class DestinationUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(DestinationCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('destinations', 'name')->ignore($keyIgnore)],
      'code' => ['required', 'string', 'max:50', Rule::unique('destinations', 'code')->ignore($keyIgnore)],
    ]);
  }
}
