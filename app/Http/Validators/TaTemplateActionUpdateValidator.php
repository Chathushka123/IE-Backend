<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class TaTemplateActionUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge(TaTemplateActionCommonValidator::getCommonRules($rec), [
      'name' => [
        'required',
        'string',
        'max:255',
        Rule::unique('ta_template_actions')->where(fn ($q) => $q->where('phase', $rec['phase'] ?? null))->ignore($keyIgnore),
      ],
    ]);
  }
}
