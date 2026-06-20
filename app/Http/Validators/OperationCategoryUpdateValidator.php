<?php

namespace App\Http\Validators;

use App\Http\Validators\OperationCategoryCommonValidator;
use Illuminate\Validation\Rule;

class OperationCategoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(OperationCategoryCommonValidator::getCommonRules(), [
      'description' => ['required', 'string', 'max:255', Rule::unique('operation_categories', 'description')->ignore($keyIgnore)],
    ]);
  }
}
