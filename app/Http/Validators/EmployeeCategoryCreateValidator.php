<?php

namespace App\Http\Validators;

use App\Http\Validators\EmployeeCategoryCommonValidator;

class EmployeeCategoryCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(EmployeeCategoryCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:employee_categories,name',
    ]);
  }
}
