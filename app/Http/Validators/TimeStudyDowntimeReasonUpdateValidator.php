<?php

namespace App\Http\Validators;

use App\Http\Validators\TimeStudyDowntimeReasonCommonValidator;
use Illuminate\Validation\Rule;

class TimeStudyDowntimeReasonUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(TimeStudyDowntimeReasonCommonValidator::getCommonRules(), [
      'name' => ['required', 'string', 'max:255', Rule::unique('time_study_downtime_reasons', 'name')->ignore($keyIgnore)],
      'code' => ['required', 'string', 'max:50', Rule::unique('time_study_downtime_reasons', 'code')->ignore($keyIgnore)],
    ]);
  }
}
