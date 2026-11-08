<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TimeStudyPlanItem extends Model
{
    protected $fillable = [
        'time_study_plan_id',
        'operation_id',
        'employee_id',
        'status',
        'skip_reason',
        'claimed_by_id',
        'claimed_until',
    ];

    protected $casts = [
        'claimed_until' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(TimeStudyPlan::class, 'time_study_plan_id');
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function claimedBy()
    {
        return $this->belongsTo(User::class, 'claimed_by_id');
    }

    public function studies()
    {
        return $this->hasMany(TimeStudy::class, 'time_study_plan_item_id');
    }
}
