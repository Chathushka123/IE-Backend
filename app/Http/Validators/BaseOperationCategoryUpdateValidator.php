<?php

namespace App\Http\Validators;

use App\Http\Validators\BaseOperationCategoryCommonValidator;
use Illuminate\Validation\Rule;

class BaseOperationCategoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(BaseOperationCategoryCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('base_operation_categories', 'name')->ignore($keyIgnore)],
      'code' => ['nullable', 'string', 'max:50', Rule::unique('base_operation_categories', 'code')->ignore($keyIgnore)],
    ]);
  }
}
