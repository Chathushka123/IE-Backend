<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TimeStudyWithParentsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'factory_id' => $this->factory_id,
            'study_date' => $this->study_date,
            'time_study_type' => $this->time_study_type,
            'operation_id' => $this->operation_id,
            'product_id' => $this->product_id,
            'employee_id' => $this->employee_id,
            'product_category_id' => $this->product_category_id,
            'machine_type_id' => $this->machine_type_id,
            'smv' => $this->smv,
            'total_productive_ms' => $this->total_productive_ms,
            'total_down_time_ms' => $this->total_down_time_ms,
            'total_hold_ms' => $this->total_hold_ms,
            'total_cycle_ms' => $this->total_cycle_ms,
            'avg_cycle_ms' => $this->avg_cycle_ms,
            'fastest_cycle_ms' => $this->fastest_cycle_ms,
            'slowest_cycle_ms' => $this->slowest_cycle_ms,
            'efficiency_pct' => $this->efficiency_pct,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'factory' => $this->whenLoaded('factory'),
            'operation' => $this->whenLoaded('operation'),
            'product' => $this->whenLoaded('product'),
            'employee' => $this->whenLoaded('employee'),
            'productCategory' => $this->whenLoaded('productCategory'),
            'machineType' => $this->whenLoaded('machineType'),
            'softSkills' => $this->whenLoaded('softSkills'),
            'laps' => $this->whenLoaded('laps'),
            'downtimes' => $this->whenLoaded('downtimes'),
        ];
    }
}
