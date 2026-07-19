<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Support\Concerns\ScopedToFactories;

class Product extends Model
{
    use ScopedToFactories;

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
        'name',
        'style_code',
        'style_description',
        'product_category_id',
        'customer_id',
        'season',
        'colors',
        'sizes',
        'customer_requested_delivery_date',
        'planned_efficiency_pct',
        'is_active',
    ];

    protected $casts = [
        'colors' => 'array',
        'sizes' => 'array',
        'customer_requested_delivery_date' => 'date:Y-m-d',
        'planned_efficiency_pct' => 'float',
    ];

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function productOperations()
    {
        return $this->hasMany(ProductOperation::class, 'product_id');
    }

    public function linePlans()
    {
        return $this->hasMany(LinePlan::class, 'product_id');
    }

    public function factories()
    {
        return $this->belongsToMany(Factory::class, 'factory_product');
    }
}
