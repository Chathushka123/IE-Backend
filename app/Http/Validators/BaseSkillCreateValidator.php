<?php

namespace App\Http\Validators;

use App\Http\Validators\BaseSkillCommonValidator;

class BaseSkillCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(BaseSkillCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:base_skills,name',
      'code' => 'required|string|max:50|unique:base_skills,code',
    ]);
  }
}
