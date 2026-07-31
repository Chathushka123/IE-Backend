<?php

namespace App\Http\Validators;

class CountryCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'required|string|max:50',
      // all_with_bc covers fixed-offset zones like Etc/GMT+12 that the default set excludes.
      'timezone' => 'required|string|timezone:all_with_bc',
      'is_active' => 'nullable|boolean',
    ];
  }
}
