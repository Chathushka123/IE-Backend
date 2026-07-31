<?php

namespace App\Http\Validators;

use App\Http\Validators\MachineCategoryCommonValidator;
use Illuminate\Validation\Rule;

class MachineCategoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(MachineCategoryCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('machine_categories', 'name')->ignore($keyIgnore)],
      'code' => ['nullable', 'string', 'max:50', Rule::unique('machine_categories', 'code')->ignore($keyIgnore)],
    ]);
  }
}
