<?php

namespace App\Http\Validators;

use App\Http\Validators\MachineCategoryCommonValidator;
use Illuminate\Validation\Rule;

class MachineCategoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(MachineCategoryCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('machine_categories', 'description')->ignore($keyIgnore)],
      'code' => ['nullable', 'string', 'max:50', Rule::unique('machine_categories', 'code')->ignore($keyIgnore)],
    ]);
  }
}
