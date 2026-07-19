<?php

namespace App\Http\Validators;

use App\Http\Validators\DepartmentCommonValidator;
use Illuminate\Validation\Rule;

class DepartmentUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(DepartmentCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($keyIgnore)],
    ]);
  }
}
