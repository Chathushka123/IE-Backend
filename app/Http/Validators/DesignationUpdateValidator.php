<?php

namespace App\Http\Validators;

use App\Http\Validators\DesignationCommonValidator;
use Illuminate\Validation\Rule;

class DesignationUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(DesignationCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('designations', 'name')->ignore($keyIgnore)],
      'code' => ['required', 'string', 'max:50', Rule::unique('designations', 'code')->ignore($keyIgnore)],
    ]);
  }
}
