<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\DepartmentRepository;

class DepartmentRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = DepartmentRepository::createRec([
            'name' => 'Cutting',
            'code' => 'CUT',
        ]);

        $this->assertDatabaseHas('departments', ['id' => $model->id, 'code' => 'CUT']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        DepartmentRepository::createRec(['name' => 'Cutting']);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        DepartmentRepository::createRec(['name' => 'Cutting', 'code' => 'CUT']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        DepartmentRepository::createRec(['name' => 'Sewing', 'code' => 'CUT']);
    }

    public function testAllowsAnUpdateThatKeepsItsOwnCode()
    {
        $this->actingAsTestUser();

        $model = DepartmentRepository::createRec(['name' => 'Cutting', 'code' => 'CUT']);

        $updated = DepartmentRepository::updateRec($model->id, [
            'name' => 'Cutting Dept',
            'code' => 'CUT',
            'updated_at' => $model->updated_at,
        ]);

        $this->assertSame('Cutting Dept', $updated->name);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = DepartmentRepository::createRec(['name' => 'Cutting', 'code' => 'CUT']);
        DepartmentRepository::createRec(['name' => 'Sewing', 'code' => 'SEW']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        DepartmentRepository::updateRec($model->id, [
            'name' => 'Cutting',
            'code' => 'SEW',
            'updated_at' => $model->updated_at,
        ]);
    }
}
