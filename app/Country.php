<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Country extends Model
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
        'timezone',
        'is_active',
    ];

    public function regions()
    {
        return $this->hasMany(Region::class);
    }

    public function factories()
    {
        return $this->hasMany(Factory::class);
    }
}
