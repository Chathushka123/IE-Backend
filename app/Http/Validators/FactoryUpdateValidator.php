<?php

namespace App\Http\Validators;

use App\Http\Validators\FactoryCommonValidator;
use Illuminate\Validation\Rule;

class FactoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(FactoryCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('factories', 'name')->ignore($keyIgnore)],
      'code' => ['required', 'string', 'max:50', Rule::unique('factories', 'code')->ignore($keyIgnore)],
    ]);
  }
}
