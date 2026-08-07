<?php

namespace App\Http\Validators;

use App\Http\Validators\EmployeeCommonValidator;
use Illuminate\Validation\Rule;

class EmployeeCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge([
      'employee_no' => [
        'required',
        'string',
        'max:50',
        Rule::unique('employees')->where('factory_id', $rec['factory_id'] ?? null),
      ],
      'identification_no' => 'required|string|max:20|unique:employees,identification_no',
    ], EmployeeCommonValidator::getCommonRules());
  }
}
