<?php

namespace App\Http\Repositories;

use App\Country;
use App\Employee;
use App\EmployeeFieldChange;
use App\ManagementHierarchy;
use App\Factory;
use App\Department;
use App\Designation;
use App\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

use App\Http\Validators\EmployeeCreateValidator;
use App\Http\Validators\EmployeeUpdateValidator;

class EmployeeRepository
{
  /** Filter keys that map straight onto an equal-named `whereIn` column — see applyExportFilters(). */
  private const EXPORT_WHERE_IN_FILTERS = [
    'gender',
    'marital_status',
    'nationality',
    'religion',
    'country_id',
    'factory_id',
    'management_hierarchy_id',
    'department_id',
    'team_id',
    'employment_type',
    'employee_status',
    'reporting_manager_id',
    'employee_category',
  ];

  /** Filter key prefix => column, for the three from/to date-range filters. */
  private const EXPORT_DATE_RANGE_FILTERS = [
    'birthday' => 'birthday',
    'joining_date' => 'joining_date',
    'created_at' => 'created_at',
  ];

  /**
   * Applies every non-empty filter (from the Export/Filter dialog) as an AND
   * condition — an unset/empty filter is simply skipped, not "match nothing".
   * Shared by EmployeesExport (Excel download) and EmployeeController::filter()
   * (the Employees table's "Filter" button) so both stay identical by construction.
   */
  public static function applyExportFilters($query, array $filters): void
  {
    foreach (self::EXPORT_WHERE_IN_FILTERS as $field) {
      $values = $filters[$field] ?? null;
      if (!empty($values)) {
        $query->whereIn($field, (array) $values);
      }
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

  /**
   * Employee fields whose changes over time form the "employee journey" —
   * assignments/statuses that can legitimately change after the employee is
   * created, as opposed to fixed identity data (name, NIC, birthday, ...).
   */
  private const TRACKED_FIELDS = [
    'marital_status',
    'factory_id',
    'management_hierarchy_id',
    'department_id',
    'team_id',
    'designation_id',
    'employment_type',
    'employee_category',
    'employee_status',
    'reporting_manager_id',
  ];

  /** Foreign-key tracked fields, mapped to the model whose `name` resolves a human-readable label. */
  private const FIELD_LOOKUP_MODELS = [
    'factory_id' => Factory::class,
    'management_hierarchy_id' => ManagementHierarchy::class,
    'department_id' => Department::class,
    'team_id' => Team::class,
    'designation_id' => Designation::class,
  ];

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      EmployeeCreateValidator::getCreateRules($rec)
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    Utilities::assertFactoryIdAllowed($rec['factory_id']);
    try {
      $model = Employee::create($rec);
      self::recordFieldChanges($model->id, array_fill_keys(self::TRACKED_FIELDS, null), $model->only(self::TRACKED_FIELDS));
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = Employee::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    $oldValues = $model->only(self::TRACKED_FIELDS);
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      EmployeeUpdateValidator::getUpdateRules($model_id, $rec)
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    Utilities::assertFactoryIdAllowed($rec['factory_id']);
    try {
      $model->update($rec);
      self::recordFieldChanges($model->id, $oldValues, $model->only(self::TRACKED_FIELDS));
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  /**
   * Returns the employee's journey — one row per tracked-field value change
   * (including the baseline set at creation), newest first.
   */
  public static function getJourney($employeeId)
  {
    return EmployeeFieldChange::where('employee_id', $employeeId)
      ->orderByDesc('created_at')
      ->orderByDesc('id')
      ->get();
  }

  /**
   * Diffs tracked-field old/new values and inserts one EmployeeFieldChange row
   * per field that actually changed, with a human-readable label snapshot for
   * FK fields so history stays readable even if the referenced record is
   * later renamed or deleted.
   */
  private static function recordFieldChanges($employeeId, array $oldValues, array $newValues)
  {
    $user = Auth::user();
    $now = now();
    $rows = [];

    foreach (self::TRACKED_FIELDS as $field) {
      $old = $oldValues[$field] ?? null;
      $new = $newValues[$field] ?? null;
      if ((string) $old === (string) $new) {
        continue;
      }
      $rows[] = [
        'employee_id' => $employeeId,
        'field' => $field,
        'old_value' => $old !== null ? (string) $old : null,
        'new_value' => $new !== null ? (string) $new : null,
        'old_label' => self::resolveFieldLabel($field, $old),
        'new_label' => self::resolveFieldLabel($field, $new),
        'changed_by_user_id' => $user->id ?? null,
        'changed_by_name' => $user->name ?? null,
        'created_at' => $now,
      ];
    }

    if (!empty($rows)) {
      EmployeeFieldChange::insert($rows);
    }
  }

  private static function resolveFieldLabel($field, $value)
  {
    if ($value === null) {
      return null;
    }

    if ($field === 'reporting_manager_id') {
      $manager = Employee::find($value);
      if (!$manager) {
        return null;
      }
      $name = $manager->full_name ?: trim($manager->first_name . ' ' . $manager->last_name);
      return "{$manager->employee_no} – {$name}";
    }

    $modelClass = self::FIELD_LOOKUP_MODELS[$field] ?? null;
    if ($modelClass === null) {
      // Plain enum/string field (marital_status, employment_type, employee_category,
      // employee_status) — the raw value is already human-readable.
      return $value;
    }

    $record = $modelClass::find($value);
    return $record ? $record->name : null;
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
    Employee::destroy($recs);
  }

  /**
   * Bulk create/update from an Excel import. Rows are matched to an existing employee by
   * employee_no + factory (employee_no is only unique per factory, not globally — see
   * migration 2026_08_07_000002). Each row is processed in its own transaction so one bad
   * row doesn't affect the rest — failures are collected and returned instead of aborting
   * the whole file.
   */
  public static function importRows($rows)
  {
    $summary = ['total' => 0, 'created' => 0, 'updated' => 0, 'failed' => []];
    $seenEmployeeNos = [];

    foreach ($rows as $index => $row) {
      $rowNumber = $index + 2; // +1 for 0-index, +1 for the heading row
      $summary['total']++;
      $employeeNo = Str::upper(trim((string) ($row['employee_no'] ?? '')));

      try {
        if ($employeeNo === '') {
          throw new Exception('Employee No is required');
        }

        $rec = self::mapImportRow($row, $employeeNo);

        $seenKey = $employeeNo . '::' . $rec['factory_id'];
        if (isset($seenEmployeeNos[$seenKey])) {
          throw new Exception("Duplicate Employee No '{$employeeNo}' in file for this factory (first seen at row {$seenEmployeeNos[$seenKey]})");
        }
        $seenEmployeeNos[$seenKey] = $rowNumber;

        $existing = Employee::where('employee_no', $employeeNo)->where('factory_id', $rec['factory_id'])->first();

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
          'employee_no' => $employeeNo ?: null,
          'error' => self::unwrapExceptionMessage($e),
        ];
      }
    }

    return $summary;
  }

  /**
   * Builds the create/update payload for one import row. Optional fields are
   * only included when the cell has a value, so a blank cell leaves an
   * existing employee's value untouched on update (Utilities::hydrate fills
   * missing keys) and lets the DB column default apply on create.
   */
  private static function mapImportRow($row, $employeeNo)
  {
    $rec = [
      'employee_no' => $employeeNo,
      'identification_no' => trim((string) ($row['identification_no'] ?? '')),
      'first_name' => trim((string) ($row['first_name'] ?? '')),
      'last_name' => trim((string) ($row['last_name'] ?? '')),
      'management_hierarchy_id' => self::resolveForeignKey(ManagementHierarchy::class, $row['management_hierarchy'] ?? null, 'Management Hierarchy', true),
      'factory_id' => self::resolveForeignKey(Factory::class, $row['factory'] ?? null, 'Factory', true),
    ];

    self::setIfNotNull($rec, 'full_name', self::blankToNull($row['full_name'] ?? null));
    self::setIfNotNull($rec, 'gender', self::blankToNull($row['gender'] ?? null));
    self::setIfNotNull($rec, 'birthday', self::parseImportDate($row['birthday'] ?? null));
    self::setIfNotNull($rec, 'email_address', self::blankToNull($row['email_address'] ?? null));
    self::setIfNotNull($rec, 'contact_no', self::joinContactNo($row['contact_country_code'] ?? null, $row['contact_no'] ?? null));
    self::setIfNotNull($rec, 'marital_status', self::blankToNull($row['marital_status'] ?? null));
    self::setIfNotNull($rec, 'nationality', self::blankToNull($row['nationality'] ?? null));
    self::setIfNotNull($rec, 'religion', self::blankToNull($row['religion'] ?? null));
    self::setIfNotNull($rec, 'street_name', self::blankToNull($row['street_name'] ?? null));
    self::setIfNotNull($rec, 'house_no', self::blankToNull($row['house_no'] ?? null));
    self::setIfNotNull($rec, 'address_line', self::blankToNull($row['address_line'] ?? null));
    self::setIfNotNull($rec, 'city_or_province', self::blankToNull($row['city_or_province'] ?? null));
    self::setIfNotNull($rec, 'postal_code', self::blankToNull($row['postal_code'] ?? null));
    self::setIfNotNull($rec, 'country_id', self::resolveForeignKey(Country::class, $row['country'] ?? null, 'Country', false));
    self::setIfNotNull($rec, 'department_id', self::resolveForeignKey(Department::class, $row['department'] ?? null, 'Department', false));
    self::setIfNotNull($rec, 'designation_id', self::resolveForeignKey(Designation::class, $row['designation'] ?? null, 'Designation', false));
    self::setIfNotNull($rec, 'employee_category', self::blankToNull($row['employee_category'] ?? null));
    self::setIfNotNull($rec, 'joining_date', self::parseImportDate($row['joining_date'] ?? null));
    self::setIfNotNull($rec, 'leaving_date', self::parseImportDate($row['leaving_date'] ?? null));
    self::setIfNotNull($rec, 'confirmation_date', self::parseImportDate($row['confirmation_date'] ?? null));
    self::setIfNotNull($rec, 'employment_type', self::blankToNull($row['employment_type'] ?? null));
    self::setIfNotNull($rec, 'reporting_manager_id', self::resolveReportingManager($row['reporting_manager'] ?? null));
    // NOTE: the Excel import-row keys 'production_line'/'base_line' are intentionally left as-is —
    // the frontend's IMPORT_HEADER_MAP still maps them from the "Team"/"Base Team" column headers
    // (see EmployeesExport.php), only the DB-facing $rec output keys and user-facing labels changed
    // to match the renamed team_id/base_team_id columns.
    self::setIfNotNull($rec, 'team_id', self::resolveForeignKey(Team::class, $row['production_line'] ?? null, 'Team', false));
    self::setIfNotNull($rec, 'base_team_id', self::resolveForeignKey(Team::class, $row['base_line'] ?? null, 'Base Team', false));
    self::setIfNotNull($rec, 'employee_status', self::blankToNull($row['employee_status'] ?? null));

    return $rec;
  }

  private static function setIfNotNull(array &$rec, $key, $value)
  {
    if ($value !== null) {
      $rec[$key] = $value;
    }
  }

  /**
   * Normalizes a raw Excel cell to a trimmed string, or null if blank.
   * Excel stores anything that looks numeric (e.g. a phone number) as an
   * int/float rather than a string, which fails the `string` validation
   * rule downstream unless cast back here.
   */
  private static function blankToNull($value)
  {
    if ($value === null) {
      return null;
    }
    $value = trim(is_string($value) ? $value : (string) $value);
    return $value === '' ? null : $value;
  }

  /**
   * Joins the "Contact Country Code" and "Contact No" import columns into the single
   * stored "<country code>-<number>" contact_no value, mirroring the frontend's
   * splitContactNo()/join in EmployeeFormModal.tsx. Defaults the country code to +94
   * (same default as the manual form) when a number is given without one.
   */
  private static function joinContactNo($countryCode, $number)
  {
    $number = self::blankToNull($number);
    if ($number === null) {
      return null;
    }
    $countryCode = self::blankToNull($countryCode) ?? '+94';
    return "{$countryCode}-{$number}";
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
    $match = $modelClass::whereRaw('LOWER(name) = ?', [Str::lower(trim($value))])->first();
    if (!$match) {
      throw new Exception("{$label} '{$value}' not found");
    }
    return $match->id;
  }

  private static function resolveReportingManager($value)
  {
    $value = self::blankToNull($value);
    if ($value === null) {
      return null;
    }
    $value = trim($value);

    $byNo = Employee::where('employee_no', $value)->first();
    if ($byNo) {
      return $byNo->id;
    }

    $byName = Employee::whereRaw('LOWER(full_name) = ?', [Str::lower($value)])->get();
    if ($byName->count() === 1) {
      return $byName->first()->id;
    }
    if ($byName->count() > 1) {
      throw new Exception("Reporting Manager '{$value}' matches multiple employees — use the employee number instead");
    }

    throw new Exception("Reporting Manager '{$value}' not found");
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
