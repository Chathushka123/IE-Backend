<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OperationGradingSkill extends Model
{
    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $user = Auth::user();
            $model->created_by_id = $user->id;
            $model->updated_by_id = $user->id;
        });
        static::updating(function ($model) {
            $user = Auth::user();
            $model->updated_by_id = $user->id;
        });
    }

    protected $table = 'operation_grading_skill';

    protected $fillable = [
        'operation_grading_id',
        'skill_id',
        'is_active',
    ];

    public function operationGrading()
    {
        return $this->belongsTo(OperationGrading::class, 'operation_grading_id');
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
