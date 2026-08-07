<?php

namespace App\Http\Validators;

use App\Http\Validators\TeamPlanCommonValidator;
use Illuminate\Validation\Rule;

class TeamPlanUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge(TeamPlanCommonValidator::getCommonRules($rec), [
      'sequence_no' => [
        'required',
        'integer',
        'min:1',
        Rule::unique('team_plans')->where('team_id', $rec['team_id'] ?? null)->ignore($keyIgnore),
      ],
    ]);
  }
}
