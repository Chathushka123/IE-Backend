<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeWithParentsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'employee_no' => $this->employee_no,
            'nic_no' => $this->nic_no,
            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'gender' => $this->gender,
            'birthday' => $this->birthday,
            'email_address' => $this->email_address,
            'contact_no' => $this->contact_no,
            'address' => $this->address,
            'marital_status' => $this->marital_status,
            'photo_url' => $this->photo_url,
            'category_id' => $this->category_id,
            'factory_id' => $this->factory_id,
            'department_id' => $this->department_id,
            'designation_id' => $this->designation_id,
            'joining_date' => $this->joining_date,
            'leaving_date' => $this->leaving_date,
            'confirmation_date' => $this->confirmation_date,
            'employment_type' => $this->employment_type,
            'reporting_manager_id' => $this->reporting_manager_id,
            'team_id' => $this->team_id,
            'base_team_id' => $this->base_team_id,
            'employee_status' => $this->employee_status,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'category' => $this->whenLoaded('category'),
            'factory' => $this->whenLoaded('factory'),
            'department' => $this->whenLoaded('department'),
            'designation' => $this->whenLoaded('designation'),
            'reportingManager' => $this->whenLoaded('reportingManager'),
            'productionLine' => $this->whenLoaded('productionLine'),
            'baseLine' => $this->whenLoaded('baseLine'),
        ];
    }
}
