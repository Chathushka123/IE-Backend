<?php

namespace App\Http\Repositories;

use App\Operation;
use App\Style;
use App\StyleOperation;
use App\ProductCategory;
use App\Customer;
use App\Factory;
use App\Season;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

use App\Http\Validators\StyleCreateValidator;
use App\Http\Validators\StyleUpdateValidator;

class StyleRepository
{
  /** Filter keys that map straight onto an equal-named `whereIn` column — see applyExportFilters(). */
  private const EXPORT_WHERE_IN_FILTERS = [
    'customer_id',
    'season_id',
    'product_category_id',
  ];

  /** Filter key prefix => column, for the two from/to date-range filters. */
  private const EXPORT_DATE_RANGE_FILTERS = [
    'created_at' => 'created_at',
    'customer_requested_delivery_date' => 'customer_requested_delivery_date',
  ];

  /**
   * Applies every non-empty filter (from the Export/Filter dialog) as an AND
   * condition — an unset/empty filter is simply skipped, not "match nothing".
   * Shared by StylesExport (Excel download) and StyleController::filter()
   * (the Styles table's "Filter" button) so both stay identical by construction.
   */
  public static function applyExportFilters($query, array $filters): void
  {
    foreach (self::EXPORT_WHERE_IN_FILTERS as $field) {
      $values = $filters[$field] ?? null;
      if (!empty($values)) {
        $query->whereIn($field, (array) $values);
      }
    }

    // product_family_id is not a column on styles: a style reaches its family through its category.
    $familyIds = $filters['product_family_id'] ?? null;
    if (!empty($familyIds)) {
      $query->whereHas('productCategory', function ($q) use ($familyIds) {
        $q->whereIn('product_family_id', (array) $familyIds);
      });
    }

    // factory_id is a many-to-many (factory_style pivot), not a column on styles.
    $factoryIds = $filters['factory_id'] ?? null;
    if (!empty($factoryIds)) {
      $query->whereHas('factories', function ($q) use ($factoryIds) {
        $q->whereIn('factories.id', (array) $factoryIds);
      });
    }

    foreach (self::EXPORT_DATE_RANGE_FILTERS as $filterPrefix => $column) {
      $from = $filters["{$filterPrefix}_from"] ?? null;
      $to = $filters["{$filterPrefix}_to"] ?? null;
      if (!empty($from)) {
        $query->whereDate($column, '>=', $from);
      }
      if (!empty($to)) {
        $query->whereDate($column, '<=', $to);
      }
    }
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      StyleCreateValidator::getCreateRules($rec)
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    if (array_key_exists('factory_ids', $rec)) {
      Utilities::assertFactoryIdsAllowed($rec['factory_ids'] ?? []);
    }
    try {
      $model = Style::create($rec);
      if (array_key_exists('factory_ids', $rec)) {
        $model->factories()->sync($rec['factory_ids'] ?? []);
      }
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  /**
   * Creates a copy of a style from the (edited) values in $rec, with the operation bulletin the caller
   * chose. The caller wraps this in a transaction so a failed bulletin leaves no half-copied style.
   *
   * Rules:
   *  - at least one of style code / product category / customer / season must differ from the
   *    source: together they are a unique key, so an identical key could never be saved (the name
   *    must differ too, which the normal create validation enforces);
   *  - $operations is the copy's whole bulletin, in order: each item is
   *    ['operation_id' => int, 'smv' => ?number, 'is_active' => ?bool]. The caller decides which of
   *    the original's operations to keep and which operations from the operation master to add;
   *    sequence numbers are assigned 1..n in the order given;
   *  - every operation must belong to the copy's product category (a style can only use operations
   *    of its own category — see StyleOperationRepository) and appear only once.
   *
   * Not copied, on purpose: milestones (order-specific dates), images, team plans and time studies.
   *
   * @param array<int,array{operation_id:int,smv?:mixed,is_active?:mixed}> $operations
   * @return array{0:Style,1:int} the new style and how many bulletin operations it was given
   */
  public static function duplicateRec($source_id, array $rec, array $operations = []): array
  {
    $source = Style::findOrFail($source_id);

    if (self::hasSameUniqueKey($source, $rec)) {
      throw new \App\Exceptions\GeneralException(
        'Change at least one of Style Code, Product Silhouette, Customer or Season to duplicate this style.'
      );
    }
    $bulletin = self::validatedBulletin($operations, (int) ($rec['product_category_id'] ?? 0));

    // A style is only visible through its factories, so a copy must not silently land in none.
    if (!array_key_exists('factory_ids', $rec)) {
      $rec['factory_ids'] = $source->factories()->pluck('factories.id')->all();
    }

    $copy = self::createRec($rec);

    foreach ($bulletin as $index => $item) {
      StyleOperation::create([
        'style_id' => $copy->id,
        'operation_id' => $item['operation_id'],
        'sequence_no' => $index + 1,
        'smv' => $item['smv'],
        'is_active' => $item['is_active'],
      ]);
    }

    return [$copy, count($bulletin)];
  }

  /**
   * Checks the chosen operations before anything is created.
   *
   * @return array<int,array{operation_id:int,smv:?float,is_active:bool}>
   */
  private static function validatedBulletin(array $operations, int $categoryId): array
  {
    $ids = array_map(fn ($item) => (int) ($item['operation_id'] ?? 0), $operations);
    if (count($ids) !== count(array_unique($ids))) {
      throw new \App\Exceptions\GeneralException('An operation can only be in the bulletin once.');
    }

    $found = Operation::whereIn('id', $ids)->get()->keyBy('id');
    $bulletin = [];
    foreach ($operations as $item) {
      $operation = $found->get((int) ($item['operation_id'] ?? 0));
      if (!$operation) {
        throw new \App\Exceptions\GeneralException('One of the selected operations no longer exists.');
      }
      if ((int) $operation->product_category_id !== $categoryId) {
        throw new \App\Exceptions\GeneralException(
          'The operation "'.($operation->description ?: $operation->code).'" does not belong to the copy\'s product silhouette.'
        );
      }
      $smv = $item['smv'] ?? null;
      if ($smv !== null && (!is_numeric($smv) || $smv < 0)) {
        throw new \App\Exceptions\GeneralException('An operation\'s SMV must be a number of 0 or more.');
      }
      $bulletin[] = [
        'operation_id' => (int) $operation->id,
        'smv' => $smv === null ? null : (float) $smv,
        'is_active' => array_key_exists('is_active', $item) ? filter_var($item['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
      ];
    }

    return $bulletin;
  }

  /** Style code compares case-insensitively, like the database collation the unique key uses. */
  private static function hasSameUniqueKey(Style $source, array $rec): bool
  {
    return strcasecmp(trim((string) $source->style_code), trim((string) ($rec['style_code'] ?? ''))) === 0
      && (int) $source->product_category_id === (int) ($rec['product_category_id'] ?? 0)
      && (int) $source->customer_id === (int) ($rec['customer_id'] ?? 0)
      && (int) $source->season_id === (int) ($rec['season_id'] ?? 0);
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = Style::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      StyleUpdateValidator::getUpdateRules($model_id, $rec)
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    if (array_key_exists('factory_ids', $rec)) {
      Utilities::assertFactoryIdsAllowed($rec['factory_ids'] ?? []);
    }
    try {
      $model->update($rec);
      if (array_key_exists('factory_ids', $rec)) {
        $model->factories()->sync($rec['factory_ids'] ?? []);
      }
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
    Style::destroy($recs);
  }

  /**
   * Bulk create/update from an Excel import. Rows are matched to an existing style
   * by Name (case-insensitively, so a manual casing tweak doesn't create a
   * duplicate). Each row is processed in its own transaction so one bad row doesn't
   * affect the rest — failures are collected and returned instead of aborting the file.
   */
  public static function importRows($rows)
  {
    $summary = ['total' => 0, 'created' => 0, 'updated' => 0, 'failed' => []];
    $seenNames = [];

    foreach ($rows as $index => $row) {
      $rowNumber = $index + 2; // +1 for 0-index, +1 for the heading row
      $summary['total']++;
      $name = trim((string) ($row['name'] ?? ''));
      $nameKey = Str::lower($name);

      try {
        if ($name === '') {
          throw new Exception('Name is required');
        }
        if (isset($seenNames[$nameKey])) {
          throw new Exception("Duplicate Name '{$name}' in file (first seen at row {$seenNames[$nameKey]})");
        }
        $seenNames[$nameKey] = $rowNumber;

        $rec = self::mapImportRow($row, $name);
        $existing = Style::whereRaw('LOWER(name) = ?', [$nameKey])->first();

        DB::beginTransaction();
        if ($existing) {
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
          'name' => $name ?: null,
          'error' => self::unwrapExceptionMessage($e),
        ];
      }
    }

    return $summary;
  }

  /**
   * Builds the create/update payload for one import row. Optional fields are only
   * included when the cell has a value, so a blank cell leaves an existing style's
   * value untouched on update (Utilities::hydrate fills missing keys) and lets the DB
   * column default apply on create.
   */
  private static function mapImportRow($row, $name)
  {
    $customerId = self::resolveForeignKey(Customer::class, $row['customer'] ?? null, 'Customer', true);

    $rec = [
      'name' => $name,
      'product_category_id' => self::resolveForeignKey(ProductCategory::class, $row['product_category'] ?? null, 'Product Category', true, 'name'),
      'customer_id' => $customerId,
    ];

    self::setIfNotNull($rec, 'style_code', self::blankToUpper($row['style_code'] ?? null));
    self::setIfNotNull($rec, 'style_description', self::blankToNull($row['style_description'] ?? null));
    self::setIfNotNull($rec, 'season_id', self::resolveSeason($row['season'] ?? null, $customerId));
    self::setIfNotNull($rec, 'colors', self::parseListField($row['colors'] ?? null));
    self::setIfNotNull($rec, 'sizes', self::parseListField($row['sizes'] ?? null));
    self::setIfNotNull($rec, 'customer_requested_delivery_date', self::parseImportDate($row['customer_requested_delivery_date'] ?? null));
    self::setIfNotNull($rec, 'planned_efficiency_pct', self::parsePercent($row['planned_efficiency_pct'] ?? null));
    self::setIfNotNull($rec, 'is_active', self::parseBoolean($row['active'] ?? null));
    self::setIfNotNull($rec, 'factory_ids', self::resolveFactoryIds($row['factories'] ?? null));

    return $rec;
  }

  private static function setIfNotNull(array &$rec, $key, $value)
  {
    if ($value !== null) {
      $rec[$key] = $value;
    }
  }

  /**
   * Normalizes a raw Excel cell to a trimmed string, or null if blank. Excel stores
   * anything that looks numeric as an int/float rather than a string, which fails the
   * `string` validation rule downstream unless cast back here.
   */
  private static function blankToNull($value)
  {
    if ($value === null) {
      return null;
    }
    $value = trim(is_string($value) ? $value : (string) $value);
    return $value === '' ? null : $value;
  }

  private static function blankToUpper($value)
  {
    $value = self::blankToNull($value);
    return $value === null ? null : Str::upper($value);
  }

  /** Splits a comma-separated cell ("Red, Navy, Black") into a trimmed string array, or null if blank. */
  private static function parseListField($value)
  {
    $value = self::blankToNull($value);
    if ($value === null) {
      return null;
    }
    $items = array_values(array_filter(array_map('trim', explode(',', $value)), fn ($item) => $item !== ''));
    return $items ?: null;
  }

  private static function parsePercent($value)
  {
    $value = self::blankToNull($value);
    if ($value === null) {
      return null;
    }
    if (!is_numeric($value)) {
      throw new Exception("Planned Efficiency % '{$value}' must be a number");
    }
    $number = (float) $value;
    if ($number < 0 || $number > 100) {
      throw new Exception("Planned Efficiency % '{$value}' must be between 0 and 100");
    }
    return $number;
  }

  private static function parseBoolean($value)
  {
    $value = self::blankToNull($value);
    if ($value === null) {
      return null;
    }
    $normalized = Str::lower($value);
    if (in_array($normalized, ['yes', 'y', 'true', '1', 'active'], true)) {
      return true;
    }
    if (in_array($normalized, ['no', 'n', 'false', '0', 'inactive'], true)) {
      return false;
    }
    throw new Exception("Active '{$value}' must be Yes or No");
  }

  private static function parseImportDate($value)
  {
    $value = self::blankToNull($value);
    if ($value === null) {
      return null;
    }
    if ($value instanceof \DateTimeInterface) {
      return $value->format('Y-m-d');
    }
    if (is_numeric($value)) {
      return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
    }
    try {
      return \Carbon\Carbon::parse($value)->format('Y-m-d');
    } catch (Exception $e) {
      throw new Exception("Invalid date '{$value}'");
    }
  }

  private static function resolveForeignKey($modelClass, $value, $label, $required, $column = 'description')
  {
    $value = self::blankToNull($value);
    if ($value === null) {
      if ($required) {
        throw new Exception("{$label} is required");
      }
      return null;
    }
    $match = $modelClass::whereRaw("LOWER({$column}) = ?", [Str::lower(trim($value))])->first();
    if (!$match) {
      throw new Exception("{$label} '{$value}' not found");
    }
    return $match->id;
  }

  /**
   * Resolves a comma-separated "Factories" cell ("QCL Horana, QCL Ratmalana") to an array
   * of factory IDs. Matching is exact (case-insensitive, trimmed) — no fuzzy correction,
   * since silently mapping a typo to the wrong factory would be worse than failing the
   * row. Any name that doesn't match an existing factory fails the whole row so a typo
   * (e.g. "QCL Horna") is never silently dropped or misassigned.
   */
  private static function resolveFactoryIds($value)
  {
    $names = self::parseListField($value);
    if ($names === null) {
      return null;
    }

    $idsByLowerName = [];
    foreach (Factory::pluck('id', 'name') as $name => $id) {
      $idsByLowerName[Str::lower(trim($name))] = $id;
    }

    $ids = [];
    $notFound = [];
    foreach ($names as $name) {
      $key = Str::lower(trim($name));
      if (array_key_exists($key, $idsByLowerName)) {
        $ids[] = $idsByLowerName[$key];
      } else {
        $notFound[] = $name;
      }
    }

    if (!empty($notFound)) {
      $list = implode("', '", $notFound);
      throw new Exception("Factories: '{$list}' not found — check spelling against the factory master list.");
    }

    return array_values(array_unique($ids));
  }

  /** Seasons are scoped to a customer, so resolution needs the row's already-resolved customer_id, not just a global name lookup. */
  private static function resolveSeason($value, $customerId)
  {
    $value = self::blankToNull($value);
    if ($value === null) {
      return null;
    }
    $match = Season::whereRaw('LOWER(name) = ?', [Str::lower(trim($value))])->where('customer_id', $customerId)->first();
    if (!$match) {
      throw new Exception("Season '{$value}' not found for this Customer");
    }
    return $match->id;
  }

  private static function unwrapExceptionMessage(Exception $e)
  {
    $decoded = json_decode($e->getMessage(), true);
    if (is_array($decoded) && !empty($decoded['err']) && is_array($decoded['err'])) {
      return implode(' ', array_filter($decoded['err']));
    }
    return $e->getMessage();
  }
}
