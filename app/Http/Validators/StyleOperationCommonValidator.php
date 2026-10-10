<?php

namespace App\Http\Validators;

class StyleOperationCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'style_id' => 'required|integer|exists:styles,id',
      'operation_id' => 'required|integer|exists:operations,id',
      'sequence_no' => 'required|integer|min:1',
      'smv' => 'nullable|numeric|min:0',
      'is_active' => 'nullable|boolean',
    ];
  }
}
