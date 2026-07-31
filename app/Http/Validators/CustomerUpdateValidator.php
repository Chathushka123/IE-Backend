<?php

namespace App\Http\Validators;

use App\Http\Validators\CustomerCommonValidator;
use Illuminate\Validation\Rule;

class CustomerUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(CustomerCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('customers', 'description')->ignore($keyIgnore)],
      'code' => ['required', 'string', 'max:50', Rule::unique('customers', 'code')->ignore($keyIgnore)],
    ]);
  }
}
