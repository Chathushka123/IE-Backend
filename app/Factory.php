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

    // Region's timezone overrides the country's default. List/report paths
    // should eager-load ['region', 'country'] to get this for free per row;
    // callers that didn't (a stray single-record lookup, a console command)
    // fall through to one direct lookup instead of silently getting back
    // null — this value feeds day-boundary queries and report formatting, so
    // it must never be unresolved. UTC is the last-resort default, matching
    // config('app.timezone').
    public function getEffectiveTimezoneAttribute()
    {
        if ($this->relationLoaded('region') && $this->region && $this->region->timezone) {
            return $this->region->timezone;
        }
        if ($this->relationLoaded('country') && $this->country) {
            return $this->country->timezone;
        }

        if (!$this->relationLoaded('region') || !$this->relationLoaded('country')) {
            $tz = \DB::table('regions')
                ->join('countries', 'countries.id', '=', 'regions.country_id')
                ->where('regions.id', $this->region_id)
                ->value(\DB::raw('COALESCE(regions.timezone, countries.timezone)'));

            if ($tz) {
                return $tz;
            }
        }

        return config('app.timezone', 'UTC');
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
