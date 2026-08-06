<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TimeStudySkill extends Model
{
    protected $fillable = [
        'time_study_id',
        'soft_skill_id',
    ];

    public function timeStudy()
    {
        return $this->belongsTo(TimeStudy::class, 'time_study_id');
    }

    public function softSkill()
    {
        return $this->belongsTo(SoftSkill::class, 'soft_skill_id');
    }
}
