<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Support\Concerns\ScopedToFactory;

class TimeStudy extends Model
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
        'factory_id',
        'study_date',
        'time_study_type',
        'operation_id',
        'product_id',
        'employee_id',
        'product_category_id',
        'machine_type_id',
        'smv',
        'total_productive_ms',
        'total_down_time_ms',
        'total_hold_ms',
        'total_cycle_ms',
        'avg_cycle_ms',
        'fastest_cycle_ms',
        'slowest_cycle_ms',
        'efficiency_pct',
    ];

    protected $casts = [
        'study_date' => 'date',
        'smv' => 'decimal:4',
        'efficiency_pct' => 'decimal:2',
    ];

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function machineType()
    {
        return $this->belongsTo(MachineType::class, 'machine_type_id');
    }

    public function softSkills()
    {
        return $this->belongsToMany(SoftSkill::class, 'time_study_skills', 'time_study_id', 'soft_skill_id');
    }

    public function laps()
    {
        return $this->hasMany(TimeStudyLap::class, 'time_study_id')->orderBy('lap_no');
    }

    public function downtimes()
    {
        return $this->hasMany(TimeStudyDowntime::class, 'time_study_id');
    }
}
