<?php

namespace App\Http\Validators;

use App\Http\Validators\StyleMilestoneCommonValidator;
use Illuminate\Validation\Rule;

class StyleMilestoneUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge(StyleMilestoneCommonValidator::getCommonRules($rec), [
      'style_id' => [
        'required',
        'integer',
        'exists:styles,id',
        Rule::unique('style_milestones')->ignore($keyIgnore),
      ],
    ]);
  }
}
