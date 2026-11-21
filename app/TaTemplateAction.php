<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * One row of the standard T&A (time & action) list — the NPI critical path planners copy onto each style.
 */
class TaTemplateAction extends Model
{
    /** Display order of the known phases; unknown (user-added) phases sort after these, alphabetically. */
    public const PHASE_ORDER = ['Development', 'Plant QCO'];

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
        'phase',
        'sequence',
        'name',
        'designation_id',
        'process_time',
        'is_active',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'is_active' => 'boolean',
    ];

    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    /** Sort key: known phases first (in PHASE_ORDER), then the rest alphabetically. */
    public static function phaseRank(?string $phase): string
    {
        $index = array_search($phase, self::PHASE_ORDER, true);

        return $index === false ? '1_'.mb_strtolower((string) $phase) : '0_'.$index;
    }
}
