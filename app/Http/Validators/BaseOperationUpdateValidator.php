<?php

namespace App\Http\Validators;

use App\Http\Validators\BaseOperationCommonValidator;
use Illuminate\Validation\Rule;

class BaseOperationUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(BaseOperationCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('base_operations', 'name')->ignore($keyIgnore)],
      'code' => ['nullable', 'string', 'max:50', Rule::unique('base_operations', 'code')->ignore($keyIgnore)],
    ]);
  }
}
