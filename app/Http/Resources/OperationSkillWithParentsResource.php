<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OperationSkillWithParentsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'operation_id' => $this->operation_id,
            'soft_skill_id' => $this->soft_skill_id,
            'is_active' => $this->is_active,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'operation' => $this->whenLoaded('operation'),
            'softSkill' => $this->whenLoaded('softSkill'),
        ];
    }
}
