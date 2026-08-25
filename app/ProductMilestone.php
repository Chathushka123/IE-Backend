<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ProductMilestone extends Model
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
        'product_id',
        'planned_quantity',
        'planned_cut_date',
        'actual_cut_date',
        'planned_production_start_date',
        'actual_production_start_date',
        'planned_production_end_date',
        'actual_production_end_date',
        'planned_finishing_date',
        'actual_finishing_date',
        'planned_final_inspection_date',
        'actual_final_inspection_date',
        'planned_ex_factory_date',
        'actual_ex_factory_date',
        'planned_cargo_received_date',
        'actual_cargo_received_date',
        'planned_etd',
        'actual_etd',
        'planned_eta',
        'actual_eta',
    ];

    protected $casts = [
        'planned_quantity' => 'integer',
        'planned_cut_date' => 'date:Y-m-d',
        'actual_cut_date' => 'date:Y-m-d',
        'planned_production_start_date' => 'date:Y-m-d',
        'actual_production_start_date' => 'date:Y-m-d',
        'planned_production_end_date' => 'date:Y-m-d',
        'actual_production_end_date' => 'date:Y-m-d',
        'planned_finishing_date' => 'date:Y-m-d',
        'actual_finishing_date' => 'date:Y-m-d',
        'planned_final_inspection_date' => 'date:Y-m-d',
        'actual_final_inspection_date' => 'date:Y-m-d',
        'planned_ex_factory_date' => 'date:Y-m-d',
        'actual_ex_factory_date' => 'date:Y-m-d',
        'planned_cargo_received_date' => 'date:Y-m-d',
        'actual_cargo_received_date' => 'date:Y-m-d',
        'planned_etd' => 'date:Y-m-d',
        'actual_etd' => 'date:Y-m-d',
        'planned_eta' => 'date:Y-m-d',
        'actual_eta' => 'date:Y-m-d',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
