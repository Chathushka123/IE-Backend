<?php

namespace App\Http\Validators;

use App\Http\Validators\EmployeeCommonValidator;
use Illuminate\Validation\Rule;

class EmployeeUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'employee_no' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_no')->ignore($keyIgnore)],
      'nic_no' => ['required', 'string', 'max:20', Rule::unique('employees', 'nic_no')->ignore($keyIgnore)],
    ], EmployeeCommonValidator::getCommonRules());
  }
}
