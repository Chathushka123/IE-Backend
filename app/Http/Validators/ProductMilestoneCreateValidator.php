<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductMilestoneCommonValidator;
use Illuminate\Validation\Rule;

class ProductMilestoneCreateValidator
{
  public static function getCreateRules(array $rec = [])
  {
    return array_merge(ProductMilestoneCommonValidator::getCommonRules($rec), [
      'product_id' => [
        'required',
        'integer',
        'exists:products,id',
        Rule::unique('product_milestones'),
      ],
    ]);
  }
}
