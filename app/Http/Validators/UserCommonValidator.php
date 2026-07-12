<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class UserCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'factory_ids' => 'nullable|array',
      'factory_ids.*' => 'integer|exists:factories,id',
    ];
  }
}
