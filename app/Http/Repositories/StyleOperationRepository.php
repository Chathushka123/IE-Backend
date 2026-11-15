<?php

namespace App\Http\Repositories;

use App\Operation;
use App\Style;
use App\StyleOperation;
use Illuminate\Support\Facades\Validator;
use Exception;

use App\Http\Validators\StyleOperationCreateValidator;
use App\Http\Validators\StyleOperationUpdateValidator;

class StyleOperationRepository
{
  private static function assertSameCategory(array $rec)
  {
    $style = Style::find($rec['style_id'] ?? null);
    $operation = Operation::find($rec['operation_id'] ?? null);

    if ($style && $operation && $style->product_category_id !== $operation->product_category_id) {
      throw new \App\Exceptions\GeneralException("This operation does not belong to the style's category.");
    }
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      StyleOperationCreateValidator::getCreateRules($rec),
      [
        'operation_id.unique' => 'This operation is already mapped to the selected style.',
        'sequence_no.unique' => 'This sequence number is already used by another operation for this style.',
      ]
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    self::assertSameCategory($rec);
    try {
      $model = StyleOperation::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = StyleOperation::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      StyleOperationUpdateValidator::getUpdateRules($model_id, $rec),
      [
        'operation_id.unique' => 'This operation is already mapped to the selected style.',
        'sequence_no.unique' => 'This sequence number is already used by another operation for this style.',
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
    StyleOperation::destroy($recs);
  }

  /**
   * Copies chosen operations from one style's bulletin onto the end of another's.
   * The caller wraps this in a transaction so a refused operation leaves nothing half-copied.
   *
   * Rules:
   *  - both styles must share a product category (an operation belongs to one category, so only
   *    then are the source's operations valid for the target);
   *  - every item must currently be in the source's bulletin and not already in the target's —
   *    refused rather than skipped, so a stale screen can't quietly copy less than the user saw;
   *  - items are appended after the target's last step in the source's own order, whatever order
   *    they were sent in;
   *  - `smv` null inherits the operation master's SMV; a number overrides it for the target only;
   *    the source's own SMV override is deliberately not carried over;
   *  - `is_active` is carried over from the source step.
   *
   * @param array<int,array{operation_id:int,smv?:mixed}> $items
   * @return \Illuminate\Database\Eloquent\Collection<int,StyleOperation> the created steps, in sequence order
   */
  public static function copyFromStyle(int $targetId, int $sourceId, array $items)
  {
    // findOrFail goes through the style factory scope: a style the user can't see can't be copied from or to.
    $target = Style::findOrFail($targetId);
    $source = Style::findOrFail($sourceId);

    if ((int) $target->id === (int) $source->id) {
      throw new \App\Exceptions\GeneralException('Choose a different style to copy operations from.');
    }
    if ((int) $target->product_category_id !== (int) $source->product_category_id) {
      throw new \App\Exceptions\GeneralException('Operations can only be copied between styles of the same product silhouette.');
    }

    $ids = array_map(fn ($item) => (int) ($item['operation_id'] ?? 0), $items);
    if (count($ids) !== count(array_unique($ids))) {
      throw new \App\Exceptions\GeneralException('An operation can only be copied once.');
    }

    $sourceSteps = StyleOperation::with('operation')
      ->where('style_id', $source->id)
      ->whereIn('operation_id', $ids)
      ->get()
      ->keyBy('operation_id');

    $smvById = [];
    foreach ($items as $item) {
      $operationId = (int) $item['operation_id'];
      if (!$sourceSteps->has($operationId)) {
        throw new \App\Exceptions\GeneralException('One of the selected operations is no longer in "'.$source->name.'". Reopen the dialog and try again.');
      }
      $smv = $item['smv'] ?? null;
      if ($smv !== null && (!is_numeric($smv) || $smv < 0)) {
        throw new \App\Exceptions\GeneralException('An operation\'s SMV must be a number of 0 or more.');
      }
      $smvById[$operationId] = $smv === null ? null : (float) $smv;
    }

    $alreadyThere = StyleOperation::with('operation')
      ->where('style_id', $target->id)
      ->whereIn('operation_id', $ids)
      ->get();
    if ($alreadyThere->isNotEmpty()) {
      $names = $alreadyThere->map(fn ($step) => $step->operation->description ?: $step->operation->code)->implode(', ');
      throw new \App\Exceptions\GeneralException('Already in "'.$target->name.'": '.$names.'. Reopen the dialog and try again.');
    }

    $sequenceNo = (int) StyleOperation::where('style_id', $target->id)->lockForUpdate()->max('sequence_no');

    $created = new \Illuminate\Database\Eloquent\Collection();
    foreach ($sourceSteps->sortBy('sequence_no') as $operationId => $sourceStep) {
      $created->push(StyleOperation::create([
        'style_id' => $target->id,
        'operation_id' => $operationId,
        'sequence_no' => ++$sequenceNo,
        'smv' => $smvById[$operationId],
        'is_active' => (bool) $sourceStep->is_active,
      ]));
    }

    return $created;
  }

  public static function resequence(int $styleId, array $ids)
  {
    $records = StyleOperation::whereIn('id', $ids)
      ->where('style_id', $styleId)
      ->get()
      ->keyBy('id');

    if ($records->count() !== count($ids)) {
      throw new \App\Exceptions\GeneralException(
        'One or more IDs do not belong to the given style.'
      );
    }

    // Null out all sequence numbers first to avoid unique constraint conflicts
    StyleOperation::whereIn('id', $ids)->update(['sequence_no' => null]);

    // Use query builder directly — Eloquent's dirty-check would skip save()
    // for any record whose new sequence_no equals its original value
    foreach ($ids as $index => $id) {
      StyleOperation::where('id', $id)->update(['sequence_no' => $index + 1]);
    }

    return StyleOperation::whereIn('id', $ids)->orderBy('sequence_no')->get();
  }
}
