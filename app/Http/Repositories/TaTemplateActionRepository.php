<?php

namespace App\Http\Repositories;

use App\Designation;
use App\TaTemplateAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

use App\Http\Validators\TaTemplateActionCreateValidator;
use App\Http\Validators\TaTemplateActionUpdateValidator;

class TaTemplateActionRepository
{
  /**
   * Every standard action, in display order: known phases first, then by sequence. The list is the
   * ~70-row NPI critical path, so it is returned whole (no pagination) and grouped client-side.
   */
  public static function listAll()
  {
    return TaTemplateAction::with('designation:id,name,code')
      ->get()
      ->sortBy(fn (TaTemplateAction $a) => [TaTemplateAction::phaseRank($a->phase), $a->sequence, $a->id])
      ->values();
  }

  public static function createRec(array $rec)
  {
    $rec = self::normalise($rec);
    $validator = Validator::make(
      $rec,
      TaTemplateActionCreateValidator::getCreateRules($rec),
      ['name.unique' => 'This phase already has an action with that name.']
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    if (empty($rec['sequence'])) {
      $rec['sequence'] = self::nextSequence($rec['phase']);
    }
    try {
      $model = TaTemplateAction::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }

    return $model->load('designation:id,name,code');
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = TaTemplateAction::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }

    $rec = self::normalise($rec);
    // Moving an action to another phase puts it at the end of that phase unless a position was given.
    if (isset($rec['phase']) && $rec['phase'] !== $model->phase && !array_key_exists('sequence', $rec)) {
      $rec['sequence'] = self::nextSequence($rec['phase']);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      TaTemplateActionUpdateValidator::getUpdateRules($model_id, $rec),
      ['name.unique' => 'This phase already has an action with that name.']
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    try {
      $model->update($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }

    return $model->load('designation:id,name,code');
  }

  public static function deleteRecs(array $recs)
  {
    // style_ta_actions.ta_template_action_id is ON DELETE SET NULL, so styles keep their own copies.
    TaTemplateAction::destroy($recs);
  }

  /**
   * Re-sequences one phase: $ids are the actions in their new order, 1..n. Actions of the phase that
   * are not listed keep their relative order after the listed ones, so a stale client list can never
   * drop or duplicate a sequence number.
   *
   * @param int[] $ids
   */
  public static function reorder(string $phase, array $ids)
  {
    $inPhase = TaTemplateAction::where('phase', $phase)->orderBy('sequence')->orderBy('id')->get()->keyBy('id');

    $ordered = [];
    foreach ($ids as $id) {
      $id = (int) $id;
      if (!$inPhase->has($id)) {
        throw new \App\Exceptions\GeneralException('One of the actions does not belong to the "'.$phase.'" phase.');
      }
      $ordered[$id] = true;
    }
    foreach ($inPhase->keys() as $id) {
      $ordered[$id] = true;
    }

    $sequence = 1;
    foreach (array_keys($ordered) as $id) {
      $action = $inPhase->get($id);
      if ($action->sequence !== $sequence) {
        $action->update(['sequence' => $sequence]);
      }
      $sequence++;
    }

    return self::listAll()->where('phase', $phase)->values();
  }

  /**
   * Finds a designation by name or code (case-insensitive). Names are only unique per department, so
   * an ambiguous name is refused instead of guessed — the sheet should use the code in that case.
   */
  public static function resolveDesignationId(string $nameOrCode): int
  {
    $needle = mb_strtolower(trim($nameOrCode));
    $matches = Designation::whereRaw('LOWER(code) = ?', [$needle])->pluck('id');
    if ($matches->isEmpty()) {
      $matches = Designation::whereRaw('LOWER(name) = ?', [$needle])->pluck('id');
    }
    if ($matches->isEmpty()) {
      throw new Exception("Designation '{$nameOrCode}' not found");
    }
    if ($matches->count() > 1) {
      throw new Exception("Designation '{$nameOrCode}' matches more than one designation — use its code instead");
    }

    return (int) $matches->first();
  }

  /**
   * Imports/updates the standard list from a sheet shaped like the export. Actions are matched by
   * phase + name (case-insensitive); a blank cell leaves the stored value untouched, so a partial
   * sheet (say, only a Designation column) is safe. New actions are appended to their phase.
   *
   * @return array{total:int,created:int,updated:int,failed:array}
   */
  public static function importRows($rows): array
  {
    $summary = ['total' => 0, 'created' => 0, 'updated' => 0, 'failed' => []];
    $seen = [];

    foreach ($rows as $index => $row) {
      $rowNumber = Utilities::importRowNumber($row, $index); // the Excel row the frontend sent (blank rows are skipped client-side)
      $summary['total']++;
      $phase = trim((string) ($row['phase'] ?? ''));
      $name = trim((string) ($row['name'] ?? ''));

      // One transaction per row, opened first so the catch's rollBack() always pairs with a begin.
      DB::beginTransaction();
      try {
        if ($phase === '' || $name === '') {
          throw new Exception('Phase and Action are required');
        }
        $key = mb_strtolower($phase.'|'.$name);
        if (isset($seen[$key])) {
          throw new Exception("Duplicate action '{$name}' in phase '{$phase}' in file (first seen at row {$seen[$key]})");
        }
        $seen[$key] = $rowNumber;

        $rec = self::mapImportRow($row);
        $existing = TaTemplateAction::whereRaw('LOWER(phase) = ?', [mb_strtolower($phase)])
          ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
          ->first();

        if ($existing) {
          self::updateRec($existing->id, array_merge($rec, ['updated_at' => $existing->updated_at]));
          DB::commit();
          $summary['updated']++;
        } else {
          self::createRec(array_merge($rec, ['phase' => $phase, 'name' => $name]));
          DB::commit();
          $summary['created']++;
        }
      } catch (Exception $e) {
        DB::rollBack();
        $decoded = json_decode($e->getMessage(), true);
        $summary['failed'][] = [
          'row' => $rowNumber,
          'name' => $name !== '' ? $name : null,
          'error' => is_array($decoded) && !empty($decoded['err']) && is_array($decoded['err'])
            ? implode(' ', array_filter($decoded['err']))
            : $e->getMessage(),
        ];
      }
    }

    return $summary;
  }

  /** Only the columns the sheet actually filled in; phase + name identify the row and are never changed here. */
  private static function mapImportRow($row): array
  {
    $rec = [];

    $sequence = trim((string) ($row['sequence'] ?? ''));
    if ($sequence !== '') {
      if (!ctype_digit($sequence) || (int) $sequence < 1) {
        throw new Exception("Sequence '{$sequence}' must be a whole number of at least 1");
      }
      $rec['sequence'] = (int) $sequence;
    }
    $designation = trim((string) ($row['designation'] ?? ''));
    if ($designation !== '') {
      $rec['designation_id'] = self::resolveDesignationId($designation);
    }
    $processTime = trim((string) ($row['process_time'] ?? ''));
    if ($processTime !== '') {
      $rec['process_time'] = $processTime;
    }
    $active = mb_strtolower(trim((string) ($row['is_active'] ?? '')));
    if ($active !== '') {
      if (in_array($active, ['yes', 'y', 'true', '1', 'active'], true)) {
        $rec['is_active'] = true;
      } elseif (in_array($active, ['no', 'n', 'false', '0', 'inactive'], true)) {
        $rec['is_active'] = false;
      } else {
        throw new Exception("Active '{$row['is_active']}' must be Yes or No");
      }
    }

    return $rec;
  }

  private static function nextSequence(string $phase): int
  {
    return (int) TaTemplateAction::where('phase', $phase)->max('sequence') + 1;
  }

  /** Trims text and turns blank strings into null so optional columns never store "". */
  private static function normalise(array $rec): array
  {
    foreach (['phase', 'name', 'process_time'] as $key) {
      if (array_key_exists($key, $rec) && is_string($rec[$key])) {
        $rec[$key] = trim($rec[$key]);
      }
    }
    foreach (['process_time', 'designation_id'] as $key) {
      if (array_key_exists($key, $rec) && $rec[$key] === '') {
        $rec[$key] = null;
      }
    }

    return $rec;
  }
}
