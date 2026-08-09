<?php

namespace App\Http\Validators;

class ManagementHierarchyCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'nullable|string|max:50',
      'seq_no' => 'required|integer|min:0',
      'is_active' => 'nullable|boolean',
    ];
  }
}
