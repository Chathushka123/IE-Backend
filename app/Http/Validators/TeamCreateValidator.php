<?php

namespace App\Http\Validators;

use App\Http\Validators\TeamCommonValidator;

class TeamCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(TeamCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:teams,name',
    ]);
  }
}
