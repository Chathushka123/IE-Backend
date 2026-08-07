<?php

namespace App\Http\Validators;

use App\Http\Validators\EmployeeCommonValidator;
use Illuminate\Validation\Rule;

class EmployeeUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge([
      'employee_no' => [
        'required',
        'string',
        'max:50',
        Rule::unique('employees')->where('factory_id', $rec['factory_id'] ?? null)->ignore($keyIgnore),
      ],
      'identification_no' => ['required', 'string', 'max:20', Rule::unique('employees', 'identification_no')->ignore($keyIgnore)],
    ], EmployeeCommonValidator::getCommonRules());
  }
}
