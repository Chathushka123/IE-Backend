<?php

namespace App\Http\Validators;

class ProductMilestoneCommonValidator
{
  public static function getCommonRules(array $rec = [])
  {
    return [
      'product_id' => 'required|integer|exists:products,id',
      'planned_quantity' => 'nullable|integer|min:1',
      'planned_cut_date' => 'nullable|date',
      'actual_cut_date' => 'nullable|date',
      'planned_production_start_date' => 'nullable|date',
      'actual_production_start_date' => 'nullable|date',
      'planned_production_end_date' => 'nullable|date',
      'actual_production_end_date' => 'nullable|date',
      'planned_finishing_date' => 'nullable|date',
      'actual_finishing_date' => 'nullable|date',
      'planned_final_inspection_date' => 'nullable|date',
      'actual_final_inspection_date' => 'nullable|date',
      'planned_ex_factory_date' => 'nullable|date',
      'actual_ex_factory_date' => 'nullable|date',
      'planned_cargo_received_date' => 'nullable|date',
      'actual_cargo_received_date' => 'nullable|date',
      'planned_etd' => 'nullable|date',
      'actual_etd' => 'nullable|date',
      'planned_eta' => 'nullable|date',
      'actual_eta' => 'nullable|date',
    ];
  }
}
