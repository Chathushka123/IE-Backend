<?php

namespace Tests\Unit\Repositories;

use App\Country;
use App\Exceptions\GeneralException;
use App\Http\Repositories\FactoryRepository;
use App\Region;

class FactoryRepositoryTest extends RepositoryTestCase
{
    private function makeCountryAndRegion(): array
    {
        $countryId = Country::create(['name' => 'Sri Lanka', 'code' => 'LK'])->id;
        $regionId = Region::create(['name' => 'Western', 'country_id' => $countryId])->id;

        return [$countryId, $regionId];
    }

    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();
        [$countryId, $regionId] = $this->makeCountryAndRegion();

        $model = FactoryRepository::createRec([
            'name' => 'Plant 1',
            'code' => 'PL1',
            'country_id' => $countryId,
            'region_id' => $regionId,
        ]);

        $this->assertDatabaseHas('factories', ['id' => $model->id, 'code' => 'PL1']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();
        [$countryId, $regionId] = $this->makeCountryAndRegion();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        FactoryRepository::createRec([
            'name' => 'Plant 1',
            'country_id' => $countryId,
            'region_id' => $regionId,
        ]);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();
        [$countryId, $regionId] = $this->makeCountryAndRegion();

        FactoryRepository::createRec(['name' => 'Plant 1', 'code' => 'PL1', 'country_id' => $countryId, 'region_id' => $regionId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        FactoryRepository::createRec(['name' => 'Plant 2', 'code' => 'PL1', 'country_id' => $countryId, 'region_id' => $regionId]);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();
        [$countryId, $regionId] = $this->makeCountryAndRegion();

        $model = FactoryRepository::createRec(['name' => 'Plant 1', 'code' => 'PL1', 'country_id' => $countryId, 'region_id' => $regionId]);
        FactoryRepository::createRec(['name' => 'Plant 2', 'code' => 'PL2', 'country_id' => $countryId, 'region_id' => $regionId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        FactoryRepository::updateRec($model->id, [
            'name' => 'Plant 1',
            'code' => 'PL2',
            'country_id' => $countryId,
            'region_id' => $regionId,
            'updated_at' => $model->updated_at,
        ]);
    }
}
