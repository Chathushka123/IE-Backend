<?php

namespace App\Http\Validators;

use App\Http\Validators\MachineTypeCommonValidator;
use Illuminate\Validation\Rule;

class MachineTypeUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(MachineTypeCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('machine_types', 'description')->ignore($keyIgnore)],
      'code' => ['nullable', 'string', 'max:50', Rule::unique('machine_types', 'code')->ignore($keyIgnore)],
    ]);
  }
}
