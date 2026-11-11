<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Middle level of the product hierarchy: ProductGroup -> ProductFamily -> ProductCategory.
 * (The UI calls a ProductCategory a "Product Silhouette".)
 * ADR: strict tree (a family belongs to exactly one group) rather than many-to-many, so a category's
 * group is unambiguous and reports/scope matching never double-count.
 */
class ProductFamily extends Model
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
        'name',
        'code',
        'product_group_id',
        'is_active',
    ];

    public function productGroup()
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    public function productCategories()
    {
        return $this->hasMany(ProductCategory::class, 'product_family_id');
    }
}
