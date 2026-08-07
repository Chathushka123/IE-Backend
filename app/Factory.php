<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Factory extends Model
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
        'is_active',
        'country_id',
        'region_id',
    ];

    protected $appends = [
        'effective_timezone',
    ];

    // Region's timezone overrides the country's default; only resolvable when
    // those relations are eager-loaded (callers must request them explicitly
    // to avoid an N+1 lazy-load per factory row).
    public function getEffectiveTimezoneAttribute()
    {
        if ($this->relationLoaded('region') && $this->region && $this->region->timezone) {
            return $this->region->timezone;
        }
        if ($this->relationLoaded('country') && $this->country) {
            return $this->country->timezone;
        }
        return null;
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'factory_user');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'factory_product');
    }
}
