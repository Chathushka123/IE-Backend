<?php

namespace App\Http\Validators;

use App\Http\Validators\TimeStudyDowntimeReasonCommonValidator;

class TimeStudyDowntimeReasonCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(TimeStudyDowntimeReasonCommonValidator::getCommonRules(), [
      'name' => 'required|string|max:255|unique:time_study_downtime_reasons,name',
      'code' => 'required|string|max:50|unique:time_study_downtime_reasons,code',
    ]);
  }
}
