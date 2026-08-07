<?php

namespace App\Http\Repositories;

use App\Employee;
use App\EmployeeCategory;
use App\Factory;
use App\Department;
use App\Designation;
use App\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

use App\Http\Validators\EmployeeCreateValidator;
use App\Http\Validators\EmployeeUpdateValidator;

class EmployeeRepository
{
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
      'category_id' => self::resolveForeignKey(EmployeeCategory::class, $row['category'] ?? null, 'Category', true),
      'factory_id' => self::resolveForeignKey(Factory::class, $row['factory'] ?? null, 'Factory', true),
    ];

    self::setIfNotNull($rec, 'full_name', self::blankToNull($row['full_name'] ?? null));
    self::setIfNotNull($rec, 'gender', self::blankToNull($row['gender'] ?? null));
    self::setIfNotNull($rec, 'birthday', self::parseImportDate($row['birthday'] ?? null));
    self::setIfNotNull($rec, 'email_address', self::blankToNull($row['email_address'] ?? null));
    self::setIfNotNull($rec, 'contact_no', self::blankToNull($row['contact_no'] ?? null));
    self::setIfNotNull($rec, 'address', self::blankToNull($row['address'] ?? null));
    self::setIfNotNull($rec, 'marital_status', self::blankToNull($row['marital_status'] ?? null));
    self::setIfNotNull($rec, 'department_id', self::resolveForeignKey(Department::class, $row['department'] ?? null, 'Department', false));
    self::setIfNotNull($rec, 'designation_id', self::resolveForeignKey(Designation::class, $row['designation'] ?? null, 'Designation', false));
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
