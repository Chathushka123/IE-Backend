<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeamPlanWithParentsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'product_id' => $this->product_id,
            'sequence_no' => $this->sequence_no,
            'planned_quantity' => $this->planned_quantity,
            'planned_start_date' => $this->planned_start_date,
            'planned_end_date' => $this->planned_end_date,
            'actual_start_date' => $this->actual_start_date,
            'actual_end_date' => $this->actual_end_date,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'team' => $this->whenLoaded('team'),
            'product' => $this->whenLoaded('product'),
        ];
    }
}
