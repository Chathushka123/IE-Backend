<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class StyleTaActionUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge(StyleTaActionCommonValidator::getCommonRules($rec), [
      'name' => [
        'required',
        'string',
        'max:255',
        Rule::unique('style_ta_actions')->where(
          fn ($q) => $q->where('style_id', $rec['style_id'] ?? null)->where('phase', $rec['phase'] ?? null)
        )->ignore($keyIgnore),
      ],
    ]);
  }
}
