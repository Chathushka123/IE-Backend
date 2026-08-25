<?php

namespace App\Http\Repositories;

use App\Product;
use App\ProductMilestone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

use App\Http\Validators\ProductMilestoneCreateValidator;
use App\Http\Validators\ProductMilestoneUpdateValidator;

class ProductMilestoneRepository
{
  /**
   * Every milestone date column, in the order a style actually moves through the
   * factory — shared by the Excel export's column order and the importer's row
   * mapping so the two never drift apart. label => db column.
   */
  const DATE_FIELDS = [
    'Planned Cutting Date' => 'planned_cut_date',
    'Actual Cutting Date' => 'actual_cut_date',
    'Planned Production Start' => 'planned_production_start_date',
    'Actual Production Start' => 'actual_production_start_date',
    'Planned Production End' => 'planned_production_end_date',
    'Actual Production End' => 'actual_production_end_date',
    'Planned Finishing Date' => 'planned_finishing_date',
    'Actual Finishing Date' => 'actual_finishing_date',
    'Planned Final Inspection' => 'planned_final_inspection_date',
    'Actual Final Inspection' => 'actual_final_inspection_date',
    'Planned Ex-Factory Date' => 'planned_ex_factory_date',
    'Actual Ex-Factory Date' => 'actual_ex_factory_date',
    'Planned Cargo Received Date' => 'planned_cargo_received_date',
    'Actual Cargo Received Date' => 'actual_cargo_received_date',
    'Planned ETD' => 'planned_etd',
    'Actual ETD' => 'actual_etd',
    'Planned ETA' => 'planned_eta',
    'Actual ETA' => 'actual_eta',
  ];

  /** Date-range filters exposed on the Export Filters dialog — the 5 dates that mark the
   *  start of each production stage, not all 18 (cutting/production/shipping milestones). */
  const EXPORT_DATE_RANGE_FILTERS = [
    'planned_cut_date' => 'planned_cut_date',
    'planned_production_start_date' => 'planned_production_start_date',
    'planned_production_end_date' => 'planned_production_end_date',
    'planned_etd' => 'planned_etd',
    'planned_eta' => 'planned_eta',
    'created_at' => 'created_at',
  ];

  public static function applyExportFilters($query, array $filters): void
  {
    $customerIds = $filters['customer_id'] ?? null;
    if (!empty($customerIds)) {
      $query->whereHas('product', function ($q) use ($customerIds) {
        $q->whereIn('customer_id', (array) $customerIds);
      });
    }

    $seasonIds = $filters['season_id'] ?? null;
    if (!empty($seasonIds)) {
      $query->whereHas('product', function ($q) use ($seasonIds) {
        $q->whereIn('season_id', (array) $seasonIds);
      });
    }

    $productCategoryIds = $filters['product_category_id'] ?? null;
    if (!empty($productCategoryIds)) {
      $query->whereHas('product', function ($q) use ($productCategoryIds) {
        $q->whereIn('product_category_id', (array) $productCategoryIds);
      });
    }

    // factory_id is a many-to-many on products (factory_product pivot), not a column.
    $factoryIds = $filters['factory_id'] ?? null;
    if (!empty($factoryIds)) {
      $query->whereHas('product.factories', function ($q) use ($factoryIds) {
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
      ProductMilestoneCreateValidator::getCreateRules($rec),
      [
        'product_id.unique' => 'This product already has a milestone record — edit it instead of creating a new one.',
      ]
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    try {
      $model = ProductMilestone::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = ProductMilestone::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      ProductMilestoneUpdateValidator::getUpdateRules($model_id, $rec),
      [
        'product_id.unique' => 'This product already has a milestone record — edit it instead of creating a new one.',
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

  public static function deleteRecs(array $recs)
  {
    ProductMilestone::destroy($recs);
  }

  /**
   * Imports/updates milestone rows from an uploaded Excel file, matched to an
   * existing product by name (same matching convention as ProductRepository's
   * own import). One row per product — a second row for an already-imported
   * product updates its existing milestone record rather than failing, since
   * a re-export-edit-reimport round trip is the expected workflow.
   */
  public static function importRows($rows)
  {
    $summary = ['total' => 0, 'created' => 0, 'updated' => 0, 'failed' => []];
    $seenProducts = [];

    foreach ($rows as $index => $row) {
      $rowNumber = $index + 2; // +1 for 0-index, +1 for the heading row
      $summary['total']++;
      $productName = trim((string) ($row['product'] ?? ''));
      $productKey = Str::lower($productName);

      try {
        if ($productName === '') {
          throw new Exception('Product is required');
        }
        if (isset($seenProducts[$productKey])) {
          throw new Exception("Duplicate Product '{$productName}' in file (first seen at row {$seenProducts[$productKey]})");
        }
        $seenProducts[$productKey] = $rowNumber;

        $productId = self::resolveProductId($productName);
        $rec = self::mapImportRow($row, $productId);
        $existing = ProductMilestone::where('product_id', $productId)->first();

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
          'name' => $productName ?: null,
          'error' => self::unwrapExceptionMessage($e),
        ];
      }
    }

    return $summary;
  }

  private static function resolveProductId(string $productName): int
  {
    $match = Product::whereRaw('LOWER(name) = ?', [Str::lower(trim($productName))])->first();
    if (!$match) {
      throw new Exception("Product '{$productName}' not found");
    }
    return $match->id;
  }

  /**
   * Builds the create/update payload for one import row. A blank cell is omitted so
   * Utilities::hydrate/model update leaves an existing milestone's value untouched
   * rather than clearing it — the same "blank means unchanged" convention as
   * ProductRepository::mapImportRow.
   */
  private static function mapImportRow($row, int $productId): array
  {
    $rec = ['product_id' => $productId];

    $plannedQuantity = trim((string) ($row['planned_quantity'] ?? ''));
    if ($plannedQuantity !== '') {
      $rec['planned_quantity'] = (int) $plannedQuantity;
    }

    foreach (self::DATE_FIELDS as $column) {
      $value = trim((string) ($row[$column] ?? ''));
      if ($value !== '') {
        $rec[$column] = $value;
      }
    }

    return $rec;
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
