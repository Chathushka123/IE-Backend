<?php

namespace App\Http\Validators;

use App\Http\Validators\EmployeeCommonValidator;

class EmployeeCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'employee_no' => 'required|string|max:50|unique:employees,employee_no',
      'nic_no' => 'required|string|max:20|unique:employees,nic_no',
    ], EmployeeCommonValidator::getCommonRules());
  }
}
