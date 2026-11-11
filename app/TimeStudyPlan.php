<?php

namespace App;

use App\Support\Concerns\ScopedToFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TimeStudyPlan extends Model
{
    use ScopedToFactory;

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
        'factory_id',
        'name',
        'template_version_id',
        'time_study_type',
        'style_id',
        'target_per_pair',
        'status',
        'activated_at',
        'completed_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(TimeStudyPlanItem::class, 'time_study_plan_id')->orderBy('operation_id')->orderBy('id');
    }

    public function templateVersion()
    {
        return $this->belongsTo(TimeStudyTemplateVersion::class, 'template_version_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
