<?php

namespace App\Http\Validators;

use App\Http\Validators\DepartmentCommonValidator;

class DepartmentCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(DepartmentCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:departments,name',
      'code' => 'required|string|max:50|unique:departments,code',
    ]);
  }
}
