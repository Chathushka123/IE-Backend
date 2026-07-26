<?php

namespace App\Http\Validators;

use App\Http\Validators\TeamCommonValidator;
use Illuminate\Validation\Rule;

class TeamUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge(TeamCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($keyIgnore)],
      'code' => [
        'required',
        'string',
        'max:50',
        Rule::unique('teams')->where('factory_id', $rec['factory_id'] ?? null)->ignore($keyIgnore),
      ],
    ]);
  }
}
