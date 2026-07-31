<?php

namespace App\Http\Repositories;

use App\BaseOperation;
use App\Operation;
use App\ProductCategory;
use App\MachineType;
use Illuminate\Support\Facades\Validator;
use Exception;

use App\Http\Validators\OperationCreateValidator;
use App\Http\Validators\OperationUpdateValidator;

class OperationRepository
{
  private static function buildDefaultDescription(array $rec)
  {
    $parts = [
      optional(BaseOperation::find($rec['base_operation_id'] ?? null))->name,
      optional(ProductCategory::find($rec['product_category_id'] ?? null))->name,
      optional(MachineType::find($rec['machine_type_id'] ?? null))->name,
    ];

    return implode(' - ', array_filter($parts));
  }

  public static function createRec(array $rec)
  {
    if (empty($rec['description'])) {
      $rec['description'] = self::buildDefaultDescription($rec);
    }

    $validator = Validator::make(
      $rec,
      OperationCreateValidator::getCreateRules($rec),
      [
        'product_category_id.unique' => 'This operation is already mapped to the selected product category.',
      ]
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    try {
      $model = Operation::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = Operation::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      OperationUpdateValidator::getUpdateRules($model_id, $rec),
      [
        'product_category_id.unique' => 'This operation is already mapped to the selected product category.',
      ]
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    try {
      $model->update($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function createMultipleRecs($master_id, array $recs)
  {
    $ret = [];
    foreach ($recs as $rec) {
      $parent_key = array_search("!PARENT_KEY!", $rec);
      if ($parent_key) {
        $rec[$parent_key] = $master_id;
      }
      $ret[] = self::createRec($rec);
    }

    return $ret;
  }

  public static function updateMultipleRecs($master_id, array $recs)
  {
    $ret = [];
    foreach ($recs as $index => $body) {
      foreach ($body as $child_id => $rec) {
        $parent_key = array_search("!PARENT_KEY!", $rec);
        if ($parent_key) {
          $rec[$parent_key] = $master_id;
        }
        $ret[] = self::updateRec($child_id, $rec);
      }
    }

    return $ret;
  }

  public static function deleteRecs(array $recs)
  {
    Operation::destroy($recs);
  }

  public static function resequence(int $productCategoryId, array $ids)
  {
    $records = Operation::whereIn('id', $ids)
      ->where('product_category_id', $productCategoryId)
      ->get()
      ->keyBy('id');

    if ($records->count() !== count($ids)) {
      throw new \App\Exceptions\GeneralException(
        'One or more IDs do not belong to the given product category.'
      );
    }

    // Null out all sequence numbers first to avoid unique constraint conflicts
    Operation::whereIn('id', $ids)->update(['sequence_no' => null]);

    // Use query builder directly — Eloquent's dirty-check would skip save()
    // for any record whose new sequence_no equals its original value
    foreach ($ids as $index => $id) {
      Operation::where('id', $id)->update(['sequence_no' => $index + 1]);
    }

    return Operation::whereIn('id', $ids)->orderBy('sequence_no')->get();
  }
}
