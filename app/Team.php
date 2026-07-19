<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Support\Concerns\ScopedToFactory;

class Team extends Model
{
    use ScopedToFactory;

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
        'section_id',
        'department_id',
        'factory_id',
        'is_active',
        'working_minutes_per_day',
        'target_efficiency_pct',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'team_id');
    }

    public function baseEmployees()
    {
        return $this->hasMany(Employee::class, 'base_team_id');
    }

    public function linePlans()
    {
        return $this->hasMany(LinePlan::class, 'team_id');
    }
}
