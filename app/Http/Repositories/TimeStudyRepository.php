<?php

namespace App\Http\Repositories;

use App\TimeStudy;
use App\TimeStudySkill;
use App\TimeStudyLap;
use App\TimeStudyDowntime;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Exception;

use App\Http\Validators\TimeStudyCreateValidator;

class TimeStudyRepository
{
  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      TimeStudyCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }

    $skillIds = $rec['skill_ids'] ?? [];
    $laps = $rec['laps'] ?? [];
    $downtimes = $rec['downtimes'] ?? [];
    $headerData = array_diff_key($rec, array_flip(['skill_ids', 'laps', 'downtimes']));
    // No explicit conductor means the logged-in user conducted the study themselves.
    $headerData['conducted_by_id'] = $headerData['conducted_by_id'] ?? Auth::id();

    // The stored cycle stats/efficiency come from non-excluded laps, so at least one must count.
    $countedLaps = array_filter($laps, fn ($lap) => empty($lap['is_excluded']));
    if (count($countedLaps) === 0) {
      throw new \App\Exceptions\GeneralException('At least one lap must be included in the calculations.');
    }

    try {
      $model = TimeStudy::create($headerData);

      foreach ($skillIds as $baseSkillId) {
        TimeStudySkill::create([
          'time_study_id' => $model->id,
          'base_skill_id' => $baseSkillId,
        ]);
      }

      foreach ($laps as $lap) {
        TimeStudyLap::create([
          'time_study_id' => $model->id,
          'lap_no' => $lap['lap_no'],
          'duration_ms' => $lap['duration_ms'],
          'down_time_ms' => $lap['down_time_ms'] ?? 0,
          'net_ms' => $lap['net_ms'],
          'is_excluded' => !empty($lap['is_excluded']),
        ]);
      }

      foreach ($downtimes as $downtime) {
        TimeStudyDowntime::create([
          'time_study_id' => $model->id,
          'lap_no' => $downtime['lap_no'] ?? null,
          'time_study_downtime_reason_id' => $downtime['time_study_downtime_reason_id'],
          'note' => $downtime['note'] ?? null,
          'duration_ms' => $downtime['duration_ms'],
        ]);
      }
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }

    return $model;
  }

  public static function deleteRecs(array $recs)
  {
    TimeStudy::destroy($recs);
  }
}
