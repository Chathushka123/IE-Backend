<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StyleOperationWithParentsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'style_id' => $this->style_id,
            'operation_id' => $this->operation_id,
            'sequence_no' => $this->sequence_no,
            'smv' => $this->smv,
            'is_active' => $this->is_active,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'style' => $this->whenLoaded('style'),
            'operation' => $this->whenLoaded('operation'),
        ];
    }
}
