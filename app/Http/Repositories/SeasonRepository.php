<?php

namespace App\Http\Repositories;

use App\Season;
use Illuminate\Support\Facades\Validator;
use Exception;

use App\Http\Validators\SeasonCreateValidator;
use App\Http\Validators\SeasonUpdateValidator;

class SeasonRepository
{
  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      SeasonCreateValidator::getCreateRules($rec)
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    try {
      $model = Season::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = Season::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      SeasonUpdateValidator::getUpdateRules($model_id, $rec)
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
    Season::destroy($recs);
  }
}
