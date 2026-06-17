<?php

namespace App\Http\Validators;

use App\Http\Validators\LineCategoryCommonValidator;
use Illuminate\Validation\Rule;

class LineCategoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(LineCategoryCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('line_categories', 'description')->ignore($keyIgnore)],
    ]);
  }
}
