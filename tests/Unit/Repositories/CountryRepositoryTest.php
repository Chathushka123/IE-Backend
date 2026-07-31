<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\CountryRepository;

class CountryRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = CountryRepository::createRec(['name' => 'Sri Lanka', 'code' => 'LK']);

        $this->assertDatabaseHas('countries', ['id' => $model->id, 'code' => 'LK']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        CountryRepository::createRec(['name' => 'Sri Lanka']);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        CountryRepository::createRec(['name' => 'Sri Lanka', 'code' => 'LK']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        CountryRepository::createRec(['name' => 'Laos', 'code' => 'LK']);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = CountryRepository::createRec(['name' => 'Sri Lanka', 'code' => 'LK']);
        CountryRepository::createRec(['name' => 'India', 'code' => 'IN']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        CountryRepository::updateRec($model->id, [
            'name' => 'Sri Lanka',
            'code' => 'IN',
            'updated_at' => $model->updated_at,
        ]);
    }
}
