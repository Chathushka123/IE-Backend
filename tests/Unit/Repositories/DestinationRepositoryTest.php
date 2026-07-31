<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\DestinationRepository;

class DestinationRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = DestinationRepository::createRec(['name' => 'Colombo Port', 'code' => 'CMB']);

        $this->assertDatabaseHas('destinations', ['id' => $model->id, 'code' => 'CMB']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        DestinationRepository::createRec(['name' => 'Colombo Port']);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        DestinationRepository::createRec(['name' => 'Colombo Port', 'code' => 'CMB']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        DestinationRepository::createRec(['name' => 'Hambantota Port', 'code' => 'CMB']);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = DestinationRepository::createRec(['name' => 'Colombo Port', 'code' => 'CMB']);
        DestinationRepository::createRec(['name' => 'Hambantota Port', 'code' => 'HTB']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        DestinationRepository::updateRec($model->id, [
            'name' => 'Colombo Port',
            'code' => 'HTB',
            'updated_at' => $model->updated_at,
        ]);
    }
}
