<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One "Recalculate & Save" event for the Skill Matrix Insights screen,
 * scoped to the factory-set (FactoryContext::ids()) it was run under.
 * Retention is "latest only" per factory-scope — see
 * SkillMatrixCalculationRepository::recalculate().
 */
class SkillMatrixCalculationRun extends Model
{
    protected $fillable = [
        'factory_ids',
        'filters',
        'study_count_total',
        'cell_count',
        'calculated_by_id',
        'calculated_at',
    ];

    protected $casts = [
        'factory_ids' => 'array',
        'filters' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function cells()
    {
        return $this->hasMany(SkillMatrixCalculationCell::class, 'calculation_run_id');
    }

    public function calculatedBy()
    {
        return $this->belongsTo(User::class, 'calculated_by_id');
    }
}
