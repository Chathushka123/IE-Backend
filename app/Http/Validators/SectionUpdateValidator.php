<?php

namespace App\Http\Validators;

use App\Http\Validators\SectionCommonValidator;
use Illuminate\Validation\Rule;

class SectionUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(SectionCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('sections', 'name')->ignore($keyIgnore)],
      'code' => ['required', 'string', 'max:50', Rule::unique('sections', 'code')->ignore($keyIgnore)],
    ]);
  }
}
