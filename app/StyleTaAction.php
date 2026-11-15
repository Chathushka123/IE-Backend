<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * One T&A (time & action) item on a style — a per-style copy of a TaTemplateAction that carries the
 * style's own owner, planned date and actual (completion) date.
 */
class StyleTaAction extends Model
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
        'style_id',
        'ta_template_action_id',
        'phase',
        'sequence',
        'name',
        'designation_id',
        'employee_id',
        'process_time',
        'planned_date',
        'actual_date',
        'remarks',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'planned_date' => 'date:Y-m-d',
        'actual_date' => 'date:Y-m-d',
    ];

    public function style()
    {
        return $this->belongsTo(Style::class, 'style_id');
    }

    public function templateAction()
    {
        return $this->belongsTo(TaTemplateAction::class, 'ta_template_action_id');
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
