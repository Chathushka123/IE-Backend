<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OperationGradingSkillWithParentsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'operation_grading_id' => $this->operation_grading_id,
            'skill_id' => $this->skill_id,
            'is_active' => $this->is_active,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'operationGrading' => $this->whenLoaded('operationGrading'),
            'skill' => $this->whenLoaded('skill'),
        ];
    }
}
