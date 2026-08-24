<?php

namespace App\Http\Validators;

use App\Http\Validators\EmployeeCommonValidator;
use Illuminate\Validation\Rule;

class EmployeeUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge([
      // employee_no is server-generated and immutable (see Employee::boot()) — not user input.
      'identification_no' => ['required', 'string', 'max:20', Rule::unique('employees', 'identification_no')->ignore($keyIgnore)],
      'epf_no' => [
        'nullable',
        'string',
        'max:50',
        Rule::unique('employees')->where('factory_id', $rec['factory_id'] ?? null)->ignore($keyIgnore),
      ],
    ], EmployeeCommonValidator::getCommonRules());
  }
}
