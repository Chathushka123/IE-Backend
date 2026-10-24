<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TimeStudyTemplateCondition extends Model
{
    protected $fillable = [
        'time_study_template_version_id',
        'field',
        'ref_id',
    ];

    public function version()
    {
        return $this->belongsTo(TimeStudyTemplateVersion::class, 'time_study_template_version_id');
    }
}
