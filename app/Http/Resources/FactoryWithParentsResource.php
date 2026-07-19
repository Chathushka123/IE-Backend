<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FactoryWithParentsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'is_active' => $this->is_active,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'productionLines' => $this->whenLoaded('productionLines'),
            'employees' => $this->whenLoaded('employees'),
            'users' => $this->whenLoaded('users'),
            'products' => $this->whenLoaded('products'),
        ];
    }
}
