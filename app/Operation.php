<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Operation extends Model
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

    protected $fillable = [
        'description',
        'code',
        'operation_category_id',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(OperationCategory::class, 'operation_category_id');
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'operation_skill', 'operation_id', 'skill_id');
    }

    public function operationGradings()
    {
        return $this->hasMany(OperationGrading::class, 'operation_id');
    }
}
