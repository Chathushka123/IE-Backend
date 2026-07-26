<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\SectionRepository;

class SectionRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = SectionRepository::createRec(['name' => 'Sewing', 'code' => 'SEW']);

        $this->assertDatabaseHas('sections', ['id' => $model->id, 'code' => 'SEW']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        SectionRepository::createRec(['name' => 'Sewing']);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        SectionRepository::createRec(['name' => 'Sewing', 'code' => 'SEW']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        SectionRepository::createRec(['name' => 'Cutting', 'code' => 'SEW']);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = SectionRepository::createRec(['name' => 'Sewing', 'code' => 'SEW']);
        SectionRepository::createRec(['name' => 'Cutting', 'code' => 'CUT']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        SectionRepository::updateRec($model->id, [
            'name' => 'Sewing',
            'code' => 'CUT',
            'updated_at' => $model->updated_at,
        ]);
    }
}
