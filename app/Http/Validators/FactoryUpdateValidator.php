<?php

namespace App\Http\Validators;

use App\Http\Validators\FactoryCommonValidator;
use Illuminate\Validation\Rule;

class FactoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(FactoryCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('factories', 'description')->ignore($keyIgnore)],
      'code' => ['nullable', 'string', 'max:50', Rule::unique('factories', 'code')->ignore($keyIgnore)],
    ]);
  }
}
