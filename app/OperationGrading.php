<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OperationGrading extends Model
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
        'operation_id',
        'product_category_id',
        'machine_type_id',
        'description',
        'code',
        'grade_id',
        'sequence_no',
        'smv',
        'is_active',
    ];

    protected $casts = [
        'smv' => 'decimal:4',
    ];

    public function operation()
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function machineType()
    {
        return $this->belongsTo(MachineType::class, 'machine_type_id');
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'operation_grading_skill', 'operation_grading_id', 'skill_id');
    }

    public function grade()
    {
        return $this->belongsTo(OperationGrade::class, 'grade_id');
    }
}
