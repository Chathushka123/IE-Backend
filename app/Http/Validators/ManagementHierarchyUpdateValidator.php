<?php

namespace App\Http\Validators;

use App\Http\Validators\ManagementHierarchyCommonValidator;
use Illuminate\Validation\Rule;

class ManagementHierarchyUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(ManagementHierarchyCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('management_hierarchies', 'name')->ignore($keyIgnore)],
      'code' => ['nullable', 'string', 'max:50', Rule::unique('management_hierarchies', 'code')->ignore($keyIgnore)],
    ]);
  }
}
