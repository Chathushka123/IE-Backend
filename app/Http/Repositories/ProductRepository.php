<?php

namespace App\Http\Repositories;

use App\Product;
use App\ProductCategory;
use App\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

use App\Http\Validators\ProductCreateValidator;
use App\Http\Validators\ProductUpdateValidator;

class ProductRepository
{
  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      ProductCreateValidator::getCreateRules()
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
      ProductUpdateValidator::getUpdateRules($model_id)
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
   * by Description (case-insensitively, so a manual casing tweak doesn't create a
   * duplicate). Each row is processed in its own transaction so one bad row doesn't
   * affect the rest — failures are collected and returned instead of aborting the file.
   */
  public static function importRows($rows)
  {
    $summary = ['total' => 0, 'created' => 0, 'updated' => 0, 'failed' => []];
    $seenDescriptions = [];

    foreach ($rows as $index => $row) {
      $rowNumber = $index + 2; // +1 for 0-index, +1 for the heading row
      $summary['total']++;
      $description = trim((string) ($row['description'] ?? ''));
      $descriptionKey = Str::lower($description);

      try {
        if ($description === '') {
          throw new Exception('Description is required');
        }
        if (isset($seenDescriptions[$descriptionKey])) {
          throw new Exception("Duplicate Description '{$description}' in file (first seen at row {$seenDescriptions[$descriptionKey]})");
        }
        $seenDescriptions[$descriptionKey] = $rowNumber;

        $rec = self::mapImportRow($row, $description);
        $existing = Product::whereRaw('LOWER(description) = ?', [$descriptionKey])->first();

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
          'description' => $description ?: null,
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
  private static function mapImportRow($row, $description)
  {
    $rec = [
      'description' => $description,
      'product_category_id' => self::resolveForeignKey(ProductCategory::class, $row['product_category'] ?? null, 'Product Category', true),
    ];

    self::setIfNotNull($rec, 'style_code', self::blankToUpper($row['style_code'] ?? null));
    self::setIfNotNull($rec, 'style_description', self::blankToNull($row['style_description'] ?? null));
    self::setIfNotNull($rec, 'customer_id', self::resolveForeignKey(Customer::class, $row['customer'] ?? null, 'Customer', false));
    self::setIfNotNull($rec, 'season', self::blankToUpper($row['season'] ?? null));
    self::setIfNotNull($rec, 'colors', self::parseListField($row['colors'] ?? null));
    self::setIfNotNull($rec, 'sizes', self::parseListField($row['sizes'] ?? null));
    self::setIfNotNull($rec, 'customer_requested_delivery_date', self::parseImportDate($row['customer_requested_delivery_date'] ?? null));
    self::setIfNotNull($rec, 'planned_efficiency_pct', self::parsePercent($row['planned_efficiency_pct'] ?? null));
    self::setIfNotNull($rec, 'is_active', self::parseBoolean($row['active'] ?? null));

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

  private static function resolveForeignKey($modelClass, $value, $label, $required)
  {
    $value = self::blankToNull($value);
    if ($value === null) {
      if ($required) {
        throw new Exception("{$label} is required");
      }
      return null;
    }
    $match = $modelClass::whereRaw('LOWER(description) = ?', [Str::lower(trim($value))])->first();
    if (!$match) {
      throw new Exception("{$label} '{$value}' not found");
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
