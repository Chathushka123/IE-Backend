<?php

namespace App\Http\Validators;

use App\Http\Validators\EmployeeCommonValidator;
use Illuminate\Validation\Rule;

class EmployeeCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge([
      // employee_no is server-generated (see Employee::boot()) — not user input.
      'identification_no' => 'required|string|max:20|unique:employees,identification_no',
      'epf_no' => [
        'nullable',
        'string',
        'max:50',
        Rule::unique('employees')->where('factory_id', $rec['factory_id'] ?? null),
      ],
    ], EmployeeCommonValidator::getCommonRules());
  }
}
