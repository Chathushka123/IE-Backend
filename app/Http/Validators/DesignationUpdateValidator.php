<?php

namespace App\Http\Validators;

use App\Http\Validators\DesignationCommonValidator;
use Illuminate\Validation\Rule;

class DesignationUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(DesignationCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('designations', 'description')->ignore($keyIgnore)],
    ]);
  }
}
