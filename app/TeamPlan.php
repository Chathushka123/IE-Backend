<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TeamPlan extends Model
{
    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $user = Auth::user();
            $model->created_by_id = $user->id;
            $model->updated_by_id = $user->id;
            if (empty($model->status)) {
                $model->status = 'planned';
            }
        });
        static::updating(function ($model) {
            $user = Auth::user();
            $model->updated_by_id = $user->id;
        });
    }

    protected $fillable = [
        'team_id',
        'product_id',
        'sequence_no',
        'planned_quantity',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'status',
        'notes',
        'is_changeover',
        'is_active',
    ];

    protected $casts = [
        'planned_quantity' => 'integer',
        'planned_start_date' => 'date:Y-m-d',
        'planned_end_date' => 'date:Y-m-d',
        'actual_start_date' => 'date:Y-m-d',
        'actual_end_date' => 'date:Y-m-d',
        'is_changeover' => 'boolean',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
