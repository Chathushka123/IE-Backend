<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\DesignationRepository;

class DesignationRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = DesignationRepository::createRec(['name' => 'Line Supervisor', 'code' => 'LSUP']);

        $this->assertDatabaseHas('designations', ['id' => $model->id, 'code' => 'LSUP']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        DesignationRepository::createRec(['name' => 'Line Supervisor']);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        DesignationRepository::createRec(['name' => 'Line Supervisor', 'code' => 'LSUP']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        DesignationRepository::createRec(['name' => 'Quality Checker', 'code' => 'LSUP']);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = DesignationRepository::createRec(['name' => 'Line Supervisor', 'code' => 'LSUP']);
        DesignationRepository::createRec(['name' => 'Quality Checker', 'code' => 'QC']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        DesignationRepository::updateRec($model->id, [
            'name' => 'Line Supervisor',
            'code' => 'QC',
            'updated_at' => $model->updated_at,
        ]);
    }
}
