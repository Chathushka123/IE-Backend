<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OperationWithParentsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'base_operation_id' => $this->base_operation_id,
            'product_category_id' => $this->product_category_id,
            'machine_type_id' => $this->machine_type_id,
            'description' => $this->description,
            'code' => $this->code,
            'grade_id' => $this->grade_id,
            'sequence_no' => $this->sequence_no,
            'smv' => $this->smv,
            'is_active' => $this->is_active,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'baseOperation' => $this->whenLoaded('baseOperation'),
            'productCategory' => $this->whenLoaded('productCategory'),
            'machineType' => $this->whenLoaded('machineType'),
            'grade' => $this->whenLoaded('grade'),
        ];
    }
}
