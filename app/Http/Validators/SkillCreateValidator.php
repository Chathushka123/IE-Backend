<?php

namespace App\Http\Validators;

use App\Http\Validators\SkillCommonValidator;

class SkillCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(SkillCommonValidator::getCommonRules(), [
      'description' => 'required|string|max:255|unique:skills,description',
      'code' => 'nullable|string|max:50|unique:skills,code',
    ]);
  }
}
