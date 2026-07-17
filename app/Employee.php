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
        'nic_no',
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
        'category_id',
        'department_id',
        'designation_id',
        'joining_date',
        'leaving_date',
        'confirmation_date',
        'employment_type',
        'reporting_manager_id',
        'production_line_id',
        'base_line_id',
        'employee_status',
    ];

    protected $casts = [
        'birthday' => 'date',
        'joining_date' => 'date',
        'leaving_date' => 'date',
        'confirmation_date' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(EmployeeCategory::class, 'category_id');
    }

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
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

    public function productionLine()
    {
        return $this->belongsTo(ProductionLine::class, 'production_line_id');
    }

    public function baseLine()
    {
        return $this->belongsTo(ProductionLine::class, 'base_line_id');
    }
}
