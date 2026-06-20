<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ProductCategory extends Model
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
        'description',
        'code',
        'product_group_id',
        'is_active',
    ];

    public function productGroup()
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    public function operationGradings()
    {
        return $this->hasMany(OperationGrading::class, 'product_category_id');
    }
}
