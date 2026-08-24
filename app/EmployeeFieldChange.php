<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmployeeFieldChange extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'employee_id',
        'field',
        'old_value',
        'new_value',
        'old_label',
        'new_label',
        'changed_by_user_id',
        'changed_by_name',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
