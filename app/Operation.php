<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Operation extends Model
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
        'base_operation_id',
        'product_category_id',
        'machine_type_id',
        'description',
        'code',
        'grade_id',
        'sequence_no',
        'smv',
        'is_active',
        'expected_top_level_efficiency',
        'expected_upper_mid_level_efficiency',
        'expected_lower_mid_level_efficiency',
    ];

    protected $casts = [
        'smv' => 'decimal:4',
        'expected_top_level_efficiency' => 'decimal:2',
        'expected_upper_mid_level_efficiency' => 'decimal:2',
        'expected_lower_mid_level_efficiency' => 'decimal:2',
    ];

    public function baseOperation()
    {
        return $this->belongsTo(BaseOperation::class, 'base_operation_id');
    }

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function machineType()
    {
        return $this->belongsTo(MachineType::class, 'machine_type_id');
    }

    public function softSkills()
    {
        return $this->belongsToMany(SoftSkill::class, 'operation_skill', 'operation_id', 'soft_skill_id');
    }

    public function grade()
    {
        return $this->belongsTo(OperationGrade::class, 'grade_id');
    }
}
