<?php

namespace App\Http\Validators;

class RoleCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'role_code' => 'required|string|max:50',
      'description' => 'required|string|max:200',
    ];
  }
}
