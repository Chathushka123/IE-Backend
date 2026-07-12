<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductWithParentsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'style_code' => $this->style_code,
            'style_description' => $this->style_description,
            'product_category_id' => $this->product_category_id,
            'customer_id' => $this->customer_id,
            'season' => $this->season,
            'colors' => $this->colors,
            'sizes' => $this->sizes,
            'customer_requested_delivery_date' => $this->customer_requested_delivery_date,
            'planned_efficiency_pct' => $this->planned_efficiency_pct,
            'is_active' => $this->is_active,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'productCategory' => $this->whenLoaded('productCategory'),
            'customer' => $this->whenLoaded('customer'),
            'factories' => $this->whenLoaded('factories'),
        ];
    }
}
