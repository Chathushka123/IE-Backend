<?php

namespace App\Http\Validators;

class TeamCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required|string|max:255',
      'code' => 'nullable|string|max:50',
      'section_id' => 'required|integer|exists:sections,id',
      'department_id' => 'required|integer|exists:departments,id',
      'factory_id' => 'required|integer|exists:factories,id',
      'is_active' => 'nullable|boolean',
    ];
  }
}
