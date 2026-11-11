<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TimeStudyTemplate extends Model
{
    /** The template every pre-template study (and every client that sends none) runs under. */
    public const LEGACY_KEY = 'legacy';

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_by_id = $model->created_by_id ?? Auth::id();
            $model->updated_by_id = $model->updated_by_id ?? Auth::id();
        });
        static::updating(function ($model) {
            $model->updated_by_id = Auth::id() ?? $model->updated_by_id;
        });
    }

    protected $fillable = [
        'name',
        'system_key',
        'purpose',
        'manual_only',
        'archived_at',
    ];

    protected $casts = [
        'manual_only' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function versions()
    {
        return $this->hasMany(TimeStudyTemplateVersion::class, 'time_study_template_id');
    }

    public function latestPublishedVersion()
    {
        return $this->hasOne(TimeStudyTemplateVersion::class, 'time_study_template_id')
            ->where('status', 'published')
            ->orderByDesc('version_no');
    }

    /** The Legacy template's single published version. */
    public static function legacyVersion(): TimeStudyTemplateVersion
    {
        return TimeStudyTemplateVersion::query()
            ->where('status', 'published')
            ->whereHas('template', fn ($q) => $q->where('system_key', self::LEGACY_KEY))
            ->orderByDesc('version_no')
            ->firstOrFail();
    }
}
