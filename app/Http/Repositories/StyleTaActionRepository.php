<?php

namespace App\Http\Repositories;

use App\Style;
use App\StyleTaAction;
use App\TaTemplateAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

use App\Http\Validators\StyleTaActionCreateValidator;
use App\Http\Validators\StyleTaActionUpdateValidator;

class StyleTaActionRepository
{
  /** Relations the UI shows next to each action. */
  private const WITH = ['designation:id,name,code', 'employee:id,employee_no,full_name,first_name,last_name,designation_id'];

  /** A style's actions in display order: known phases first (as on the master), then sequence. */
  public static function byStyle($styleId)
  {
    // whereHas('style') applies Style's factory scope: no peeking at a style outside the user's factories.
    return StyleTaAction::with(self::WITH)
      ->where('style_id', $styleId)
      ->whereHas('style')
      ->get()
      ->sortBy(fn (StyleTaAction $a) => [TaTemplateAction::phaseRank($a->phase), $a->sequence, $a->id])
      ->values();
  }

  public static function createRec(array $rec)
  {
    $rec = self::normalise($rec);
    $validator = Validator::make(
      $rec,
      StyleTaActionCreateValidator::getCreateRules($rec),
      ['name.unique' => 'This style already has an action with that name in this phase.']
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    // Style is factory-scoped; the plain "exists" rule above can't see that.
    Style::findOrFail((int) $rec['style_id']);
    if (empty($rec['sequence'])) {
      $rec['sequence'] = self::nextSequence((int) $rec['style_id'], $rec['phase']);
    }
    try {
      $model = StyleTaAction::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }

    return $model->load(self::WITH);
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = StyleTaAction::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }

    $rec = self::normalise($rec);
    if (isset($rec['phase']) && $rec['phase'] !== $model->phase && !array_key_exists('sequence', $rec)) {
      $rec['sequence'] = self::nextSequence((int) $model->style_id, $rec['phase']);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      StyleTaActionUpdateValidator::getUpdateRules($model_id, $rec),
      ['name.unique' => 'This style already has an action with that name in this phase.']
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    try {
      $model->update($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }

    return $model->load(self::WITH);
  }

  public static function deleteRecs(array $recs)
  {
    StyleTaAction::destroy($recs);
  }

  /**
   * Copies the standard T&A list onto a style. Idempotent: an action the style already has (same
   * template row, or same phase + name for a hand-added one) is skipped, so applying twice — or
   * after the master gained new actions — only adds what is missing. Dates and owners start empty
   * except the template's designation and process time.
   *
   * @param int[]|null $templateIds null = every active standard action
   * @return array{created:int,skipped:int}
   */
  public static function applyTemplate(int $styleId, ?array $templateIds = null): array
  {
    // findOrFail so an unknown (or not visible to this user) style is a clean 404-style error.
    Style::findOrFail($styleId);

    $templates = TaTemplateAction::where('is_active', true)
      ->when($templateIds !== null, fn ($q) => $q->whereIn('id', $templateIds))
      ->orderBy('sequence')->orderBy('id')
      ->get();

    $existing = StyleTaAction::where('style_id', $styleId)->get();
    $haveTemplateIds = $existing->pluck('ta_template_action_id')->filter()->flip();
    $haveNames = $existing->mapWithKeys(fn ($a) => [mb_strtolower($a->phase.'|'.$a->name) => true]);

    $created = 0;
    $skipped = 0;
    foreach ($templates as $template) {
      if ($haveTemplateIds->has($template->id) || $haveNames->has(mb_strtolower($template->phase.'|'.$template->name))) {
        $skipped++;
        continue;
      }
      StyleTaAction::create([
        'style_id' => $styleId,
        'ta_template_action_id' => $template->id,
        'phase' => $template->phase,
        'sequence' => $template->sequence,
        'name' => $template->name,
        'designation_id' => $template->designation_id,
        'process_time' => $template->process_time,
      ]);
      $created++;
    }

    return ['created' => $created, 'skipped' => $skipped];
  }

  /**
   * Copies chosen actions of one style onto another (used by "Duplicate style"). Planned and actual
   * dates are deliberately NOT copied — they belong to the original order — but the plan's shape
   * (owner, process time, remarks, order) is. The caller wraps this in a transaction.
   *
   * @param int[] $sourceActionIds ids of the source style's actions to copy
   * @return int how many actions were copied
   */
  public static function copyToStyle(int $sourceStyleId, int $targetStyleId, array $sourceActionIds): int
  {
    $ids = array_values(array_unique(array_map('intval', $sourceActionIds)));
    if (empty($ids)) {
      return 0;
    }

    $actions = StyleTaAction::where('style_id', $sourceStyleId)->whereIn('id', $ids)
      ->orderBy('sequence')->orderBy('id')->get();
    if ($actions->count() !== count($ids)) {
      throw new \App\Exceptions\GeneralException('One of the selected T&A actions no longer exists on the original style.');
    }

    foreach ($actions as $action) {
      StyleTaAction::create([
        'style_id' => $targetStyleId,
        'ta_template_action_id' => $action->ta_template_action_id,
        'phase' => $action->phase,
        'sequence' => $action->sequence,
        'name' => $action->name,
        'designation_id' => $action->designation_id,
        'employee_id' => $action->employee_id,
        'process_time' => $action->process_time,
        'remarks' => $action->remarks,
      ]);
    }

    return $actions->count();
  }

  // ── Tracker (cross-style follow-up) ────────────────────────────────────────

  public const STATUSES = ['done', 'overdue', 'due_soon', 'upcoming', 'not_scheduled'];

  /** An action is "due soon" when its planned date falls within this many days from today. */
  public const DUE_SOON_DAYS = 7;

  /** Status is derived (never stored): it changes with the calendar, not with an edit. */
  public static function statusFor(StyleTaAction $action, string $today): string
  {
    if ($action->actual_date !== null) {
      return 'done';
    }
    if ($action->planned_date === null) {
      return 'not_scheduled';
    }
    $planned = $action->planned_date->toDateString();
    if ($planned < $today) {
      return 'overdue';
    }

    return $planned <= self::soonLimit($today) ? 'due_soon' : 'upcoming';
  }

  private static function soonLimit(string $today): string
  {
    return \Carbon\Carbon::parse($today)->addDays(self::DUE_SOON_DAYS)->toDateString();
  }

  /** The caller's local date when given (a planner's "today" is not the server's UTC date), else the server date. */
  public static function resolveToday(?string $today): string
  {
    if ($today !== null && $today !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
      return $today;
    }

    return now()->toDateString();
  }

  /**
   * Narrows $query by every filter except `status` (so the tracker's summary tiles stay stable while
   * a status tile is selected). Always goes through the `style` relation so a user only ever sees
   * actions of styles in their own factories (Style is factory-scoped).
   */
  public static function applyFilters($query, array $filters): void
  {
    $query->whereHas('style', function ($q) use ($filters) {
      if (!empty($filters['style_id'])) {
        $q->whereIn('styles.id', (array) $filters['style_id']);
      }
      if (!empty($filters['customer_id'])) {
        $q->whereIn('styles.customer_id', (array) $filters['customer_id']);
      }
      if (!empty($filters['season_id'])) {
        $q->whereIn('styles.season_id', (array) $filters['season_id']);
      }
    });

    foreach (['phase' => 'phase', 'designation_id' => 'designation_id', 'employee_id' => 'employee_id'] as $key => $column) {
      if (!empty($filters[$key])) {
        $query->whereIn($column, (array) $filters[$key]);
      }
    }
    if (!empty($filters['due_from'])) {
      $query->whereDate('planned_date', '>=', $filters['due_from']);
    }
    if (!empty($filters['due_to'])) {
      $query->whereDate('planned_date', '<=', $filters['due_to']);
    }
    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
      $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
      $query->where(function ($q) use ($like) {
        $q->where('name', 'like', $like)
          ->orWhereHas('style', fn ($s) => $s->where('styles.name', 'like', $like)->orWhere('styles.style_code', 'like', $like));
      });
    }
  }

  /** Narrows $query to one derived status (see statusFor()). */
  public static function applyStatus($query, string $status, string $today): void
  {
    $soon = self::soonLimit($today);
    switch ($status) {
      case 'done':
        $query->whereNotNull('actual_date');
        break;
      case 'overdue':
        $query->whereNull('actual_date')->whereNotNull('planned_date')->whereDate('planned_date', '<', $today);
        break;
      case 'due_soon':
        $query->whereNull('actual_date')->whereDate('planned_date', '>=', $today)->whereDate('planned_date', '<=', $soon);
        break;
      case 'upcoming':
        $query->whereNull('actual_date')->whereDate('planned_date', '>', $soon);
        break;
      case 'not_scheduled':
        $query->whereNull('actual_date')->whereNull('planned_date');
        break;
      default:
        throw new \App\Exceptions\GeneralException('Unknown status "'.$status.'".');
    }
  }

  /** @return array{total:int,done:int,overdue:int,due_soon:int,upcoming:int,not_scheduled:int} */
  public static function summary(array $filters, string $today): array
  {
    $query = StyleTaAction::query();
    self::applyFilters($query, $filters);
    $soon = self::soonLimit($today);

    $row = $query->selectRaw(
      'COUNT(*) AS total,
       SUM(CASE WHEN actual_date IS NOT NULL THEN 1 ELSE 0 END) AS done,
       SUM(CASE WHEN actual_date IS NULL AND planned_date IS NOT NULL AND planned_date < ? THEN 1 ELSE 0 END) AS overdue,
       SUM(CASE WHEN actual_date IS NULL AND planned_date >= ? AND planned_date <= ? THEN 1 ELSE 0 END) AS due_soon,
       SUM(CASE WHEN actual_date IS NULL AND planned_date > ? THEN 1 ELSE 0 END) AS upcoming,
       SUM(CASE WHEN actual_date IS NULL AND planned_date IS NULL THEN 1 ELSE 0 END) AS not_scheduled',
      [$today, $today, $soon, $soon]
    )->first();

    return [
      'total' => (int) $row->total,
      'done' => (int) $row->done,
      'overdue' => (int) $row->overdue,
      'due_soon' => (int) $row->due_soon,
      'upcoming' => (int) $row->upcoming,
      'not_scheduled' => (int) $row->not_scheduled,
    ];
  }

  /** Query for the tracker list / export: filtered, status-narrowed and ordered most-urgent first. */
  public static function trackerQuery(array $filters, string $today)
  {
    $query = StyleTaAction::with(['style:id,name,style_code', 'designation:id,name,code', 'employee:id,employee_no,full_name,first_name,last_name,designation_id']);
    self::applyFilters($query, $filters);
    if (!empty($filters['status'])) {
      self::applyStatus($query, (string) $filters['status'], $today);
    }

    // done last; then overdue (oldest first), due soon, upcoming, not scheduled.
    return $query->orderByRaw(
      'CASE WHEN actual_date IS NOT NULL THEN 4 WHEN planned_date IS NULL THEN 3 WHEN planned_date < ? THEN 0 WHEN planned_date <= ? THEN 1 ELSE 2 END',
      [$today, self::soonLimit($today)]
    )->orderBy('planned_date')->orderBy('id');
  }

  // ── Bulk schedule (suggested dates) ─────────────────────────────────────────

  /**
   * Sets planned dates on several of one style's actions at once (the "Suggest dates" preview).
   * All-or-nothing: the caller wraps this in a transaction, and any id that is not this style's
   * action aborts the whole batch so a stale preview can't half-apply.
   *
   * @param array<int,array{id:mixed,planned_date:mixed}> $items
   * @return int how many actions were updated
   */
  public static function applySchedule(int $styleId, array $items): int
  {
    Style::findOrFail($styleId);

    $dates = [];
    foreach ($items as $item) {
      $id = (int) ($item['id'] ?? 0);
      $date = $item['planned_date'] ?? null;
      if ($id <= 0 || !is_string($date) || strtotime($date) === false) {
        throw new \App\Exceptions\GeneralException('Every scheduled action needs an id and a valid planned date.');
      }
      $dates[$id] = \Carbon\Carbon::parse($date)->toDateString();
    }
    if (empty($dates)) {
      return 0;
    }

    $actions = StyleTaAction::where('style_id', $styleId)->whereIn('id', array_keys($dates))->get();
    if ($actions->count() !== count($dates)) {
      throw new \App\Exceptions\GeneralException('One of the actions no longer exists on this style — reload and try again.');
    }
    foreach ($actions as $action) {
      $action->update(['planned_date' => $dates[$action->id]]);
    }

    return $actions->count();
  }

  // ── Excel import ────────────────────────────────────────────────────────────

  /**
   * Imports/updates a style's T&A actions from an uploaded sheet (the same shape the export writes).
   * Style is matched by name, the action by phase + name within that style — a missing action is
   * created as a custom one. A blank cell leaves the existing value untouched (same convention as
   * the milestone importer), so a re-export → edit dates → re-import round trip is safe.
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
      $styleName = trim((string) ($row['style'] ?? ''));
      $phase = trim((string) ($row['phase'] ?? ''));
      $name = trim((string) ($row['name'] ?? ''));

      // One transaction per row, opened first so the catch's rollBack() always pairs with a begin.
      DB::beginTransaction();
      try {
        if ($styleName === '' || $phase === '' || $name === '') {
          throw new Exception('Style, Phase and Action are required');
        }
        $key = mb_strtolower($styleName.'|'.$phase.'|'.$name);
        if (isset($seen[$key])) {
          throw new Exception("Duplicate action '{$name}' for style '{$styleName}' in file (first seen at row {$seen[$key]})");
        }
        $seen[$key] = $rowNumber;

        $style = Style::whereRaw('LOWER(name) = ?', [mb_strtolower($styleName)])->first();
        if (!$style) {
          throw new Exception("Style '{$styleName}' not found");
        }
        $rec = self::mapImportRow($row, (int) $style->id, $phase, $name);
        $existing = StyleTaAction::where('style_id', $style->id)
          ->whereRaw('LOWER(phase) = ?', [mb_strtolower($phase)])
          ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
          ->first();

        if ($existing) {
          // Keep the stored spelling/casing of phase + name; only the other columns change.
          $rec['phase'] = $existing->phase;
          $rec['name'] = $existing->name;
          self::updateRec($existing->id, array_merge($rec, ['updated_at' => $existing->updated_at]));
          DB::commit();
          $summary['updated']++;
        } else {
          self::createRec($rec);
          DB::commit();
          $summary['created']++;
        }
      } catch (Exception $e) {
        DB::rollBack();
        $summary['failed'][] = [
          'row' => $rowNumber,
          'name' => $name !== '' ? $name : null,
          'error' => self::unwrapExceptionMessage($e),
        ];
      }
    }

    return $summary;
  }

  private static function mapImportRow($row, int $styleId, string $phase, string $name): array
  {
    $rec = ['style_id' => $styleId, 'phase' => $phase, 'name' => $name];

    $sequence = trim((string) ($row['sequence'] ?? ''));
    if ($sequence !== '') {
      if (!ctype_digit($sequence) || (int) $sequence < 1) {
        throw new Exception("Sequence '{$sequence}' must be a whole number of at least 1");
      }
      $rec['sequence'] = (int) $sequence;
    }
    $designation = trim((string) ($row['designation'] ?? ''));
    if ($designation !== '') {
      $rec['designation_id'] = TaTemplateActionRepository::resolveDesignationId($designation);
    }
    $employeeNo = trim((string) ($row['employee_no'] ?? ''));
    if ($employeeNo !== '') {
      $employee = \App\Employee::where('employee_no', $employeeNo)->first();
      if (!$employee) {
        throw new Exception("Employee No '{$employeeNo}' not found");
      }
      $rec['employee_id'] = (int) $employee->id;
    }
    foreach (['process_time', 'remarks'] as $column) {
      $value = trim((string) ($row[$column] ?? ''));
      if ($value !== '') {
        $rec[$column] = $value;
      }
    }
    foreach (['planned_date' => 'Planned Date', 'actual_date' => 'Actual Date'] as $column => $label) {
      $date = Utilities::parseImportDate($row[$column] ?? null, $label);
      if ($date !== null) {
        $rec[$column] = $date;
      }
    }

    return $rec;
  }

  /** The validators report errors as JSON {"err":[...]}; show readers the plain sentence. */
  private static function unwrapExceptionMessage(Exception $e)
  {
    $decoded = json_decode($e->getMessage(), true);
    if (is_array($decoded) && !empty($decoded['err']) && is_array($decoded['err'])) {
      return implode(' ', array_filter($decoded['err']));
    }

    return $e->getMessage();
  }

  private static function nextSequence(int $styleId, string $phase): int
  {
    return (int) StyleTaAction::where('style_id', $styleId)->where('phase', $phase)->max('sequence') + 1;
  }

  /** Trims text and turns blank strings into null so optional columns never store "". */
  private static function normalise(array $rec): array
  {
    foreach (['phase', 'name', 'process_time', 'remarks'] as $key) {
      if (array_key_exists($key, $rec) && is_string($rec[$key])) {
        $rec[$key] = trim($rec[$key]);
      }
    }
    foreach (['process_time', 'remarks', 'designation_id', 'employee_id', 'planned_date', 'actual_date'] as $key) {
      if (array_key_exists($key, $rec) && $rec[$key] === '') {
        $rec[$key] = null;
      }
    }

    return $rec;
  }
}
