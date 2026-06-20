<?php

namespace App\Http\Validators;

use App\Http\Validators\SkillCommonValidator;
use Illuminate\Validation\Rule;

class SkillUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(SkillCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('skills', 'description')->ignore($keyIgnore)],
    ]);
  }
}
