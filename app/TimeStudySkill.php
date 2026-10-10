<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TimeStudySkill extends Model
{
    protected $fillable = [
        'time_study_id',
        'base_skill_id',
    ];

    public function timeStudy()
    {
        return $this->belongsTo(TimeStudy::class, 'time_study_id');
    }

    public function baseSkill()
    {
        return $this->belongsTo(BaseSkill::class, 'base_skill_id');
    }
}
