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
            'identification_no' => $this->identification_no,
            'epf_no' => $this->epf_no,
            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'gender' => $this->gender,
            'birthday' => $this->birthday,
            'email_address' => $this->email_address,
            'contact_no' => $this->contact_no,
            'address' => $this->address,
            'street_name' => $this->street_name,
            'house_no' => $this->house_no,
            'address_line' => $this->address_line,
            'city_or_province' => $this->city_or_province,
            'postal_code' => $this->postal_code,
            'country_id' => $this->country_id,
            'marital_status' => $this->marital_status,
            'photo_url' => $this->photo_url,
            'management_hierarchy_id' => $this->management_hierarchy_id,
            'factory_id' => $this->factory_id,
            'department_id' => $this->department_id,
            'designation_id' => $this->designation_id,
            'joining_date' => $this->joining_date,
            'leaving_date' => $this->leaving_date,
            'confirmation_date' => $this->confirmation_date,
            'employment_type' => $this->employment_type,
            'employee_category' => $this->employee_category,
            'reporting_manager_id' => $this->reporting_manager_id,
            'team_id' => $this->team_id,
            'base_team_id' => $this->base_team_id,
            'employee_status' => $this->employee_status,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'management_hierarchy' => $this->whenLoaded('managementHierarchy'),
            'factory' => $this->whenLoaded('factory'),
            'department' => $this->whenLoaded('department'),
            'designation' => $this->whenLoaded('designation'),
            'country' => $this->whenLoaded('country'),
            'reportingManager' => $this->whenLoaded('reportingManager'),
            'team' => $this->whenLoaded('team'),
            'baseTeam' => $this->whenLoaded('baseTeam'),
        ];
    }
}
