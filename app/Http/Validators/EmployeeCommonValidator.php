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
      'marital_status' => 'nullable|in:Single,Married,Divorced,Other',
      'photo_url' => 'nullable|string|max:500',
      'category_id' => 'required|integer|exists:employee_categories,id',
      'department_id' => 'nullable|integer|exists:departments,id',
      'designation_id' => 'nullable|integer|exists:designations,id',
      'joining_date' => 'nullable|date',
      'leaving_date' => 'nullable|date',
      'confirmation_date' => 'nullable|date',
      'employment_type' => 'nullable|in:Permanent,Contract,Casual',
      'reporting_manager_id' => 'nullable|integer|exists:employees,id',
      'production_line_id' => 'nullable|integer|exists:production_lines,id',
      'base_line_id' => 'nullable|integer|exists:production_lines,id',
      'employee_status' => 'nullable|in:Active,Resigned,Terminated',
    ];
  }
}
