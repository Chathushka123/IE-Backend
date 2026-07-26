<?php

namespace App\Http\Validators;

use App\Http\Validators\MachineTypeCommonValidator;

class MachineTypeCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(MachineTypeCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:machine_types,name',
      'code' => 'required|string|max:50|unique:machine_types,code',
    ]);
  }
}
