<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class TaTemplateActionCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(TaTemplateActionCommonValidator::getCommonRules($rec), [
      // An action name is unique within its phase.
      'name' => [
        'required',
        'string',
        'max:255',
        Rule::unique('ta_template_actions')->where(fn ($q) => $q->where('phase', $rec['phase'] ?? null)),
      ],
    ]);
  }
}
