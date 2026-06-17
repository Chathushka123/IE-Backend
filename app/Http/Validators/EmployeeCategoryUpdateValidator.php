<?php

namespace App\Http\Validators;

use App\Http\Validators\EmployeeCategoryCommonValidator;
use Illuminate\Validation\Rule;

class EmployeeCategoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(EmployeeCategoryCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('employee_categories', 'description')->ignore($keyIgnore)],
    ]);
  }
}
