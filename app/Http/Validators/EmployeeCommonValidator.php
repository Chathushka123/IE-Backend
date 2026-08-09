<?php

namespace App\Http\Validators;

class EmployeeCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'first_name' => 'required|string|max:100',
      'last_name' => 'required|string|max:100',
      'full_name' => 'nullable|string|max:255',
      'gender' => 'nullable|in:Male,Female,Other',
      'birthday' => 'nullable|date',
      'email_address' => 'nullable|email|max:255',
      'contact_no' => 'nullable|string|max:20',
      'address' => 'nullable|string|max:500',
      'street_name' => 'nullable|string|max:255',
      'house_no' => 'nullable|string|max:50',
      'address_line' => 'nullable|string|max:255',
      'city_or_province' => 'nullable|string|max:255',
      'postal_code' => 'nullable|string|max:20',
      'country_id' => 'nullable|integer|exists:countries,id',
      'marital_status' => 'nullable|in:Single,Married,Divorced,Other',
      'photo_url' => 'nullable|string|max:500',
      'management_hierarchy_id' => 'required|integer|exists:management_hierarchies,id',
      'factory_id' => 'required|integer|exists:factories,id',
      'department_id' => 'nullable|integer|exists:departments,id',
      'designation_id' => 'nullable|integer|exists:designations,id',
      'joining_date' => 'nullable|date',
      'leaving_date' => 'nullable|date',
      'confirmation_date' => 'nullable|date',
      'employment_type' => 'nullable|in:Permanent,Contract,Casual',
      'employee_category' => 'nullable|in:Direct,Indirect',
      'reporting_manager_id' => 'nullable|integer|exists:employees,id',
      'team_id' => 'nullable|integer|exists:teams,id',
      'base_team_id' => 'nullable|integer|exists:teams,id',
      'employee_status' => 'nullable|in:Active,Resigned,Terminated',
    ];
  }
}
