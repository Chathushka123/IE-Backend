<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class LinePlan extends Model
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
        'production_line_id',
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
        'planned_start_date' => 'datetime:Y-m-d H:i:s',
        'planned_end_date' => 'datetime:Y-m-d H:i:s',
        'actual_start_date' => 'datetime:Y-m-d H:i:s',
        'actual_end_date' => 'datetime:Y-m-d H:i:s',
        'is_changeover' => 'boolean',
    ];

    public function productionLine()
    {
        return $this->belongsTo(ProductionLine::class, 'production_line_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
