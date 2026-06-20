<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OperationGradingWithParentsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'operation_id' => $this->operation_id,
            'product_category_id' => $this->product_category_id,
            'grade_id' => $this->grade_id,
            'sequence_no' => $this->sequence_no,
            'smv' => $this->smv,
            'is_active' => $this->is_active,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'operation' => $this->whenLoaded('operation'),
            'productCategory' => $this->whenLoaded('productCategory'),
            'grade' => $this->whenLoaded('grade'),
        ];
    }
}
