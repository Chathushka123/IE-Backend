<?php

namespace App\Http\Validators;

use App\Http\Validators\SoftSkillCommonValidator;
use Illuminate\Validation\Rule;

class SoftSkillUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(SoftSkillCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('soft_skills', 'name')->ignore($keyIgnore)],
      'code' => ['nullable', 'string', 'max:50', Rule::unique('soft_skills', 'code')->ignore($keyIgnore)],
    ]);
  }
}
