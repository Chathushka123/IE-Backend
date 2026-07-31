<?php

namespace App\Http\Validators;

use App\Http\Validators\TeamCommonValidator;
use Illuminate\Validation\Rule;

class TeamCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(TeamCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:teams,name',
      'code' => [
        'required',
        'string',
        'max:50',
        Rule::unique('teams')->where('factory_id', $rec['factory_id'] ?? null),
      ],
    ]);
  }
}
