<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TimeStudyLap extends Model
{
    protected $fillable = [
        'time_study_id',
        'lap_no',
        'duration_ms',
        'down_time_ms',
        'net_ms',
        'is_excluded',
        'exclusion_source',
        'outlier_flagged',
        'outlier_kept',
    ];

    protected $casts = [
        'is_excluded' => 'boolean',
        'outlier_flagged' => 'boolean',
        'outlier_kept' => 'boolean',
    ];

    public function timeStudy()
    {
        return $this->belongsTo(TimeStudy::class, 'time_study_id');
    }
}
