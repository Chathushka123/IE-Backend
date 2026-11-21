<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Utilities
{
  public static function destroy(Model $model, Request $request)
  {
    $partial = "false";
    $ret = [];
    $err = false;
    try {
      DB::beginTransaction();

      $partial = $request->all()['partial'];
      $data = $request->all()['id'];
      if ($partial == "true") {
        for ($i = 0; $i <= sizeof($data) - 1; $i++) {
          try {
            $entity = (new \ReflectionClass($model))->getShortName();
            if (in_array($entity, ["Fpo", "Fppo", "JobCard"]) && ($model->status == "Closed")) {
              switch ($entity) {
                case 'Fpo':
                  throw new \App\Exceptions\NoModificationsAllowedException("FPO", "Closed");
                  break;
                case 'Fppo':
                  throw new \App\Exceptions\NoModificationsAllowedException("FPPO", "Closed");
                  break;
                case 'JobCard':
                  throw new \App\Exceptions\NoModificationsAllowedException("Job Card", "Closed");
                  break;

                default:
                  # code...
                  break;
              }
            }
            $model::destroy($data[$i]);
          } catch (Exception $e) {
            $err = true;
            $ret[] = ["id" => $data[$i], "error" => $e->getMessage()];
          }
        }
      } else {
        $model::destroy($data);
      }

      DB::commit();

      return response()->json(
        [
          'status' => $err ? 'warning' : 'success',
          'message' => $err ? 'Some resources were not deleted!' : 'Resource(s) were deleted!',
          'data' => $ret
        ],
        200
      );
    } catch (Exception $e) {
      DB::rollBack();
      return response()->json(
        [
          'status' => 'error',
          'message' => $e->getMessage()
        ],
        401
      );
    }
  }

  public static function hydrate($model, &$rec)
  {
    $columns = Schema::getColumnListing($model->getTable());
    foreach ($columns as $column) {
      if (!in_array($column, ['id', 'created_at', 'updated_at'])) {
        // if (DB::getSchemaBuilder()->getColumnType($model->getTable(), $column) == 'json') {
        //   $val = json_decode($model->$column);
        // } else {
        $val = $model->$column;
        
        // }
        // $rec[$column] = isset($rec[$column]) ? $rec[$column] : $val;
        $rec[$column] = array_key_exists($column, $rec) ? $rec[$column] : $val;
      }
    }
  }

  public static function readFile($fileNameWithPath)
  {
    /**  Identify the type of $fileNameWithPath  **/
    $inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($fileNameWithPath);

    /**  Create a new Reader of the type that has been identified  **/
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
    $reader->setReadDataOnly(true);

    /**  Load $fileNameWithPath to a Spreadsheet Object  **/
    $spreadsheet = $reader->load($fileNameWithPath);

    /**  Convert Spreadsheet Object to an Array for ease of use  **/
    return $spreadsheet->getActiveSheet()->toArray();
  }

  public static function discardColumns(&$lines, $discardColumns)
  {
    if (empty($discardColumns)) {
      return;
    }

    foreach ($lines as &$line) {
      foreach ($discardColumns as $discardColumn) {
        unset($line[$discardColumn]);
      }
      $line = array_values($line);
    }
  }

  // remove last element since it has a null value when Excel file is read
  public static function removeEmptyLines($lines)
  {
    return array_values(array_filter($lines, function ($line) {
      return !empty(array_filter($line, function ($item) {
        return $item !== null;
      }));
    }));
  }

  public static function prepareHeader($header, $fieldMapping)
  {
    foreach ($header as $key => $value) {
      foreach ($fieldMapping as $field => $target) {
        if ($value == $field) {
          if (is_array($target)) {
            if ($target['rule'] == 'foreign') {
              $header[$key] = $target['db_field'];
            }
          } else {
            $header[$key] = $target;
          }
        }
      }
    }

    return $header;
  }

  public static function getForeignKeyValue($model, $search_key, $search_value)
  {
    $class = "App\\" . $model;
    return $class::where($search_key, $search_value)->firstOrFail()->id;
  }

  public static function extractHeader(&$lines)
  {
    return array_shift($lines);
  }

  public static function prepareForeignKeyValues($header, $fieldMapping, &$line)
  {
    foreach ($header as $index => $value) {
      if (isset($fieldMapping[$value]) && is_array($fieldMapping[$value])) {
        if ($fieldMapping[$value]['rule'] == 'foreign') {
          $line[$index] = self::getForeignKeyValue($fieldMapping[$value]['model'], $value, $line[$index]);
        }
      }
    }
  }

  public static function fillJsonValues($inJsonArray, $fillingValue = 0)
  {
    foreach ($inJsonArray as $key => $value) {
      if ($value == "") {
        $inJsonArray[$key] = $fillingValue;
      }
    }

    return $inJsonArray;
  }

  public static function sortQtyJson($sort_order, $qty_json)
  {
    $newJson = [];
    foreach ($sort_order as $key => $item) {
      $newJson[$item] = $qty_json[$item];
    }
    return $newJson;
  }

  public static function fillJsonMissingSizesValues($expected_sizes, $qty_json)
  {
    foreach ($expected_sizes as $size) {
      if (!(array_key_exists($size, $qty_json))) {
        $qty_json[$size] = 0;
      }
    }
    return $qty_json;
  }

  public static function json_compare($json_1, $json_2)
  {
    foreach ($json_2 as $key => $value) {
      if ($json_1[$key] > $value) {
        return true;
      }
    }
    foreach ($json_1 as $key => $value) {
      if (isset($json_2[$key]) && $json_2[$key] < $value) {
        return true;
      }
      else if(!isset($json_2[$key]) && $value > 0){
        return true;
      }
    }
    return false;
  }

  public static function json_subtract($json_1, $json_2)
  {
    $ret = [];
    foreach ($json_1 as $key => $value) {
      $ret[$key] = $json_1[$key] - (array_key_exists($key, $json_2) ? $json_2[$key] : 0);
    }
    return $ret;
  }

  /**
   * @param array $json An array with key/value pairs whose value will be numerize
   * @param string $type [int,float] Dictates the type to convert to
   * @return array
   */
  public static function json_numerize($json, $type)
  {
    $json = array_map(function ($item) use ($type) {
      switch ($type) {
        case 'int':
          $item = (int)$item;
          break;
        case 'float':
          $item = (float)$item;
          break;
        default:
          throw new Exception("Undefined numerical conversion type.");
          break;
      }
      return $item;
    }, $json);
    return $json;
  }

  /**
   * Normalises one date cell from an Excel import to Y-m-d, or null when blank.
   *
   * Laravel's `date` rule accepts loose text such as "23-11-2026" (strtotime), but MySQL then rejects it
   * with SQLSTATE 22007 — so importers must hand the database a strict Y-m-d. Text is read day-first
   * (this app's convention), matching the frontend pre-check; anything else raises a message a user can act on.
   *
   * @throws Exception when the value is not a real calendar date
   */
  public static function parseImportDate($value, string $label = 'Date'): ?string
  {
    if ($value === null || (is_string($value) && trim($value) === '')) {
      return null;
    }
    if ($value instanceof \DateTimeInterface) {
      return $value->format('Y-m-d');
    }

    $text = trim((string) $value);
    // Strip a trailing time part ("2026-11-23 00:00:00" / "...T00:00:00Z"); only the calendar date matters.
    $text = preg_replace('/[T ]\d{1,2}:\d{2}(:\d{2}(\.\d+)?)?Z?$/', '', $text);

    // Explicit shapes with a mandatory 4-digit year: PHP's own formats would read "23-11-26" as the year 0023.
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m)) {
      [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $text, $m)) {
      [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    }
    if (isset($year) && checkdate($month, $day, $year)) {
      return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    throw new Exception("{$label} '{$text}' isn't a valid date. Use yyyy-mm-dd (e.g. 2026-11-23) or dd-mm-yyyy (e.g. 23-11-2026).");
  }

  /** Excel row number to report for an import row: the real sheet row the frontend sent, else position + heading row. */
  public static function importRowNumber($row, $index): int
  {
    return isset($row['_row']) && is_numeric($row['_row']) ? (int) $row['_row'] : $index + 2;
  }

  public static function extractError($validator)
  {
    $err = "";
    foreach (json_decode($validator->errors()) as $column => $errors) {
      $err = $errors[0];
    }
    throw new \App\Exceptions\GeneralException($err);
  }

  public static function validateCode($db_value, $frontend_value, $desc)
  {
    if ($db_value != $frontend_value) {
      throw new \App\Exceptions\GeneralException($desc . " cannot be modified.");
    }
  }

  /** Throws unless $factoryId is one of the current request's active factories. */
  public static function assertFactoryIdAllowed($factoryId)
  {
    if (\App\Support\FactoryContext::isBypassed() || !\App\Support\FactoryContext::isResolved()) {
      return;
    }
    if (!in_array((int) $factoryId, \App\Support\FactoryContext::ids(), true)) {
      throw new \App\Exceptions\GeneralException("You do not have access to the selected factory.");
    }
  }

  /** Throws unless every id in $factoryIds is one of the current request's active factories. */
  public static function assertFactoryIdsAllowed(array $factoryIds)
  {
    if (\App\Support\FactoryContext::isBypassed() || !\App\Support\FactoryContext::isResolved()) {
      return;
    }
    $allowed = \App\Support\FactoryContext::ids();
    $invalid = array_diff(array_map('intval', $factoryIds), $allowed);
    if (!empty($invalid)) {
      throw new \App\Exceptions\GeneralException("You do not have access to one or more of the selected factories.");
    }
  }

  public static function NVL($value, $revalue){
    return is_null($value) ? $revalue : $value;
  }

  public static function createStyleString($style_fit_json){
    $sizeString=[];
    foreach($style_fit_json as $sizefit){
      $sizeString[] =  $sizefit['fit'] . $sizefit['size'];
    }
    return $sizeString;
  }
}
