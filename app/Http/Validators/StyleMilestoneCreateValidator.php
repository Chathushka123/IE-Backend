<?php

namespace App\Http\Validators;

use App\Http\Validators\StyleMilestoneCommonValidator;
use Illuminate\Validation\Rule;

class StyleMilestoneCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(StyleMilestoneCommonValidator::getCommonRules($rec), [
      'style_id' => [
        'required',
        'integer',
        'exists:styles,id',
        Rule::unique('style_milestones'),
      ],
    ]);
  }
}
