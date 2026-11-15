<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class StyleTaActionCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(StyleTaActionCommonValidator::getCommonRules($rec), [
      // An action name is unique within a style's phase.
      'name' => [
        'required',
        'string',
        'max:255',
        Rule::unique('style_ta_actions')->where(
          fn ($q) => $q->where('style_id', $rec['style_id'] ?? null)->where('phase', $rec['phase'] ?? null)
        ),
      ],
    ]);
  }
}
