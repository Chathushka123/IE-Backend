<?php

namespace App\Http\Validators;

use App\Http\Validators\BaseSkillCommonValidator;
use Illuminate\Validation\Rule;

class BaseSkillUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(BaseSkillCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('base_skills', 'name')->ignore($keyIgnore)],
      'code' => ['required', 'string', 'max:50', Rule::unique('base_skills', 'code')->ignore($keyIgnore)],
    ]);
  }
}
