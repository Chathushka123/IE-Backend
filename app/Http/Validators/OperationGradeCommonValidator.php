<?php

namespace App\Http\Validators;

class OperationGradeCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'required|string|max:50',
      'level' => 'nullable|integer|min:1',
      'is_active' => 'nullable|boolean',
    ];
  }
}
