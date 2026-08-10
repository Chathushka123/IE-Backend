<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One employee x operation cell's descriptive stats within a
 * SkillMatrixCalculationRun. Kept rich (mean/median/mode/min/max/stddev)
 * rather than one lossy number so this table doubles as training data for a
 * future ML model built on top of this screen.
 */
class SkillMatrixCalculationCell extends Model
{
    protected $fillable = [
        'calculation_run_id',
        'employee_id',
        'operation_id',
        'study_count',
        'mean_efficiency',
        'median_efficiency',
        'mode_efficiency',
        'mode_bin_size_pct',
        'mode_used_fallback_to_mean',
        'min_efficiency',
        'max_efficiency',
        'stddev_efficiency',
        'calculation_method_used',
        'selected_efficiency',
        'first_study_date',
        'last_study_date',
    ];

    protected $casts = [
        'study_count' => 'integer',
        'mean_efficiency' => 'decimal:2',
        'median_efficiency' => 'decimal:2',
        'mode_efficiency' => 'decimal:2',
        'mode_bin_size_pct' => 'integer',
        'mode_used_fallback_to_mean' => 'boolean',
        'min_efficiency' => 'decimal:2',
        'max_efficiency' => 'decimal:2',
        'stddev_efficiency' => 'decimal:4',
        'selected_efficiency' => 'decimal:2',
        'first_study_date' => 'date',
        'last_study_date' => 'date',
    ];

    public function run()
    {
        return $this->belongsTo(SkillMatrixCalculationRun::class, 'calculation_run_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }
}
