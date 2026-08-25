<?php

namespace App\Http\Validators;

use App\Http\Validators\ProductMilestoneCommonValidator;
use Illuminate\Validation\Rule;

class ProductMilestoneUpdateValidator
{
  public static function getUpdateRules($keyIgnore, array $rec = [])
  {
    return array_merge(ProductMilestoneCommonValidator::getCommonRules($rec), [
      'product_id' => [
        'required',
        'integer',
        'exists:products,id',
        Rule::unique('product_milestones')->ignore($keyIgnore),
      ],
    ]);
  }
}
