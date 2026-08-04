<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TimeStudyDowntime extends Model
{
    protected $fillable = [
        'time_study_id',
        'lap_no',
        'time_study_downtime_reason_id',
        'note',
        'duration_ms',
    ];

    public function timeStudy()
    {
        return $this->belongsTo(TimeStudy::class, 'time_study_id');
    }

    public function reason()
    {
        return $this->belongsTo(TimeStudyDowntimeReason::class, 'time_study_downtime_reason_id');
    }
}
