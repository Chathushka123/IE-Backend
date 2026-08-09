<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Support\Concerns\ScopedToFactory;

class Employee extends Model
{
    use ScopedToFactory;

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
        'factory_id',
        'employee_no',
        'identification_no',
        'full_name',
        'first_name',
        'last_name',
        'gender',
        'birthday',
        'email_address',
        'contact_no',
        'address',
        'marital_status',
        'photo_url',
        'street_name',
        'house_no',
        'address_line',
        'city_or_province',
        'postal_code',
        'country_id',
        'management_hierarchy_id',
        'department_id',
        'designation_id',
        'joining_date',
        'leaving_date',
        'confirmation_date',
        'employment_type',
        'employee_category',
        'reporting_manager_id',
        'team_id',
        'base_team_id',
        'employee_status',
    ];

    protected $casts = [
        'birthday' => 'date',
        'joining_date' => 'date',
        'leaving_date' => 'date',
        'confirmation_date' => 'date',
    ];

    public function managementHierarchy()
    {
        return $this->belongsTo(ManagementHierarchy::class, 'management_hierarchy_id');
    }

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function reportingManager()
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'reporting_manager_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function baseTeam()
    {
        return $this->belongsTo(Team::class, 'base_team_id');
    }
}
