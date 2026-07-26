<?php

namespace App\Http\Validators;

use App\Http\Validators\SoftSkillCommonValidator;

class SoftSkillCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(SoftSkillCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:soft_skills,name',
      'code' => 'required|string|max:50|unique:soft_skills,code',
    ]);
  }
}
