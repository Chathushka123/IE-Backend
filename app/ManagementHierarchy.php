<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ManagementHierarchy extends Model
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
        'name',
        'code',
        'seq_no',
        'is_active',
        'retirement_age',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'management_hierarchy_id');
    }
}
