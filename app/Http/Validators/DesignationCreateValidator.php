<?php

namespace App\Http\Validators;

use App\Http\Validators\DesignationCommonValidator;

class DesignationCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(DesignationCommonValidator::getCommonRules(), [
      'description' => 'required|string|max:255|unique:designations,description',
    ]);
  }
}
