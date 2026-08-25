<?php

namespace App\Http\Repositories;

use App\Product;
use App\ProductCategory;
use App\Customer;
use App\Factory;
use App\Season;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

use App\Http\Validators\ProductCreateValidator;
use App\Http\Validators\ProductUpdateValidator;

class ProductRepository
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
   * Shared by ProductsExport (Excel download) and ProductController::filter()
   * (the Products table's "Filter" button) so both stay identical by construction.
   */
  public static function applyExportFilters($query, array $filters): void
  {
    foreach (self::EXPORT_WHERE_IN_FILTERS as $field) {
      $values = $filters[$field] ?? null;
      if (!empty($values)) {
        $query->whereIn($field, (array) $values);
      }
    }

    // factory_id is a many-to-many (factory_product pivot), not a column on products.
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
      ProductCreateValidator::getCreateRules($rec)
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    if (array_key_exists('factory_ids', $rec)) {
      Utilities::assertFactoryIdsAllowed($rec['factory_ids'] ?? []);
    }
    try {
      $model = Product::create($rec);
      if (array_key_exists('factory_ids', $rec)) {
        $model->factories()->sync($rec['factory_ids'] ?? []);
      }
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = Product::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      ProductUpdateValidator::getUpdateRules($model_id, $rec)
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
    Product::destroy($recs);
  }

  /**
   * Bulk create/update from an Excel import. Rows are matched to an existing product
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
        $existing = Product::whereRaw('LOWER(name) = ?', [$nameKey])->first();

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
   * included when the cell has a value, so a blank cell leaves an existing product's
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
