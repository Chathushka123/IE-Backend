<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ProductOperationGrading extends Model
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
        'operation_grading_id',
        'sequence_no',
        'smv',
        'is_active',
    ];

    protected $casts = [
        'smv' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function operationGrading()
    {
        return $this->belongsTo(OperationGrading::class, 'operation_grading_id');
    }
}
