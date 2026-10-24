<?php

namespace App;

use App\Exceptions\GeneralException;
use Illuminate\Database\Eloquent\Model;

class TimeStudyTemplateVersion extends Model
{
    public static function boot()
    {
        parent::boot();
        // Published versions are the record of what a study was measured against: never edited.
        static::updating(function ($model) {
            if ($model->getOriginal('status') === 'published') {
                throw new GeneralException('A published template version cannot be changed. Edit the template to create a new version.');
            }
        });
        static::deleting(function ($model) {
            if ($model->status === 'published') {
                throw new GeneralException('A published template version cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'time_study_template_id',
        'version_no',
        'status',
        'definition',
        'published_at',
        'published_by_id',
    ];

    protected $casts = [
        'definition' => 'array',
        'published_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(TimeStudyTemplate::class, 'time_study_template_id');
    }

    public function conditions()
    {
        return $this->hasMany(TimeStudyTemplateCondition::class, 'time_study_template_version_id');
    }
}
