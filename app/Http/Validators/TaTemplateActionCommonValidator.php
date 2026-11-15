<?php

namespace App\Http\Validators;

class TaTemplateActionCommonValidator
{
  public static function getCommonRules(array $rec = [])
  {
    return [
      'phase' => 'required|string|max:50',
      'sequence' => 'nullable|integer|min:1',
      'name' => 'required|string|max:255',
      'designation_id' => 'nullable|integer|exists:designations,id',
      'process_time' => 'nullable|string|max:100',
      'is_active' => 'nullable|boolean',
    ];
  }
}
