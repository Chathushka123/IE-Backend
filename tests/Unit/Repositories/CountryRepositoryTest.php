<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\CountryRepository;

class CountryRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = CountryRepository::createRec(['name' => 'Sri Lanka', 'code' => 'LK', 'timezone' => 'Asia/Colombo']);

        $this->assertDatabaseHas('countries', ['id' => $model->id, 'code' => 'LK']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        CountryRepository::createRec(['name' => 'Sri Lanka', 'timezone' => 'Asia/Colombo']);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        CountryRepository::createRec(['name' => 'Sri Lanka', 'code' => 'LK', 'timezone' => 'Asia/Colombo']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        CountryRepository::createRec(['name' => 'Laos', 'code' => 'LK', 'timezone' => 'Asia/Vientiane']);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = CountryRepository::createRec(['name' => 'Sri Lanka', 'code' => 'LK', 'timezone' => 'Asia/Colombo']);
        CountryRepository::createRec(['name' => 'India', 'code' => 'IN', 'timezone' => 'Asia/Kolkata']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        CountryRepository::updateRec($model->id, [
            'name' => 'Sri Lanka',
            'code' => 'IN',
            'updated_at' => $model->updated_at,
        ]);
    }
}
