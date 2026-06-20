<?php

namespace App\Http\Validators;

use App\Http\Validators\OperationCommonValidator;
use Illuminate\Validation\Rule;

class OperationUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(OperationCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('operations', 'description')->ignore($keyIgnore)],
      'code' => ['nullable', 'string', 'max:50', Rule::unique('operations', 'code')->ignore($keyIgnore)],
    ]);
  }
}
