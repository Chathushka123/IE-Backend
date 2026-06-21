<?php

namespace App\Http\Repositories;

use App\OperationGrading;
use App\Product;
use App\ProductOperationGrading;
use Illuminate\Support\Facades\Validator;
use Exception;

use App\Http\Validators\ProductOperationGradingCreateValidator;
use App\Http\Validators\ProductOperationGradingUpdateValidator;

class ProductOperationGradingRepository
{
  private static function assertSameCategory(array $rec)
  {
    $product = Product::find($rec['product_id'] ?? null);
    $grading = OperationGrading::find($rec['operation_grading_id'] ?? null);

    if ($product && $grading && $product->product_category_id !== $grading->product_category_id) {
      throw new \App\Exceptions\GeneralException("This operation grading does not belong to the product's category.");
    }
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      ProductOperationGradingCreateValidator::getCreateRules($rec),
      [
        'operation_grading_id.unique' => 'This operation grading is already mapped to the selected product.',
        'sequence_no.unique' => 'This sequence number is already used by another operation grading for this product.',
      ]
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    self::assertSameCategory($rec);
    try {
      $model = ProductOperationGrading::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = ProductOperationGrading::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      ProductOperationGradingUpdateValidator::getUpdateRules($model_id, $rec),
      [
        'operation_grading_id.unique' => 'This operation grading is already mapped to the selected product.',
        'sequence_no.unique' => 'This sequence number is already used by another operation grading for this product.',
      ]
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    self::assertSameCategory($rec);
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
    ProductOperationGrading::destroy($recs);
  }

  public static function resequence(int $productId, array $ids)
  {
    $records = ProductOperationGrading::whereIn('id', $ids)
      ->where('product_id', $productId)
      ->get()
      ->keyBy('id');

    if ($records->count() !== count($ids)) {
      throw new \App\Exceptions\GeneralException(
        'One or more IDs do not belong to the given product.'
      );
    }

    // Null out all sequence numbers first to avoid unique constraint conflicts
    ProductOperationGrading::whereIn('id', $ids)->update(['sequence_no' => null]);

    // Use query builder directly — Eloquent's dirty-check would skip save()
    // for any record whose new sequence_no equals its original value
    foreach ($ids as $index => $id) {
      ProductOperationGrading::where('id', $id)->update(['sequence_no' => $index + 1]);
    }

    return ProductOperationGrading::whereIn('id', $ids)->orderBy('sequence_no')->get();
  }
}
