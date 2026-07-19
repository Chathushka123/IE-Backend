<?php

namespace App\Http\Validators;

use App\Http\Validators\TeamCommonValidator;
use Illuminate\Validation\Rule;

class TeamUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(TeamCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($keyIgnore)],
    ]);
  }
}
