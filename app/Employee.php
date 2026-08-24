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
        // employee_no is server-generated from the row's id (see `created` below), so the
        // column is nullable at the DB level to allow the initial INSERT before the id is
        // known — an update slipping a value in here would leave it silently overridable.
        static::created(function ($model) {
            $model->employee_no = 'EMP-' . str_pad($model->id, 5, '0', STR_PAD_LEFT);
            $model->saveQuietly();
        });
        static::updating(function ($model) {
            $user = Auth::user();
            $model->updated_by_id = $user->id;
            if ($model->isDirty('employee_no')) {
                $model->employee_no = $model->getOriginal('employee_no');
            }
        });
    }

    protected $fillable = [
        'factory_id',
        'employee_no',
        'identification_no',
        'epf_no',
        'full_name',
        'first_name',
        'last_name',
        'gender',
        'birthday',
        'email_address',
        'contact_no',
        'address',
        'marital_status',
        'nationality',
        'religion',
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

    // Derived, not stored: birthday + the employee's management hierarchy's
    // retirement_age. Recomputes on every read so it never goes stale if
    // either value changes later — avoids the invalidation problem a
    // persisted column would need (e.g. a hierarchy's retirement_age
    // changing would otherwise require recomputing every employee under it).
    protected $appends = ['retirement_date'];

    public function getRetirementDateAttribute(): ?string
    {
        if (!$this->birthday || !$this->managementHierarchy) {
            return null;
        }

        return $this->birthday->copy()
            ->addYears($this->managementHierarchy->retirement_age)
            ->toDateString();
    }

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
