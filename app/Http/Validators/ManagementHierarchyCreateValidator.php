<?php

namespace App\Http\Validators;

use App\Http\Validators\ManagementHierarchyCommonValidator;

class ManagementHierarchyCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(ManagementHierarchyCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:management_hierarchies,name',
      'code' => 'nullable|string|max:50|unique:management_hierarchies,code',
    ]);
  }
}
