<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\EmployeeCategoryRepository;

class EmployeeCategoryRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = EmployeeCategoryRepository::createRec(['name' => 'Operator', 'code' => 'OPR']);

        $this->assertDatabaseHas('employee_categories', ['id' => $model->id, 'code' => 'OPR']);
    }

    /** code is intentionally optional here, unlike the other tables in this batch. */
    public function testAllowsMultipleRecordsWithNoCode()
    {
        $this->actingAsTestUser();

        $first = EmployeeCategoryRepository::createRec(['name' => 'Operator']);
        $second = EmployeeCategoryRepository::createRec(['name' => 'Supervisor']);

        $this->assertDatabaseHas('employee_categories', ['id' => $first->id, 'code' => null]);
        $this->assertDatabaseHas('employee_categories', ['id' => $second->id, 'code' => null]);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        EmployeeCategoryRepository::createRec(['name' => 'Operator', 'code' => 'OPR']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        EmployeeCategoryRepository::createRec(['name' => 'Supervisor', 'code' => 'OPR']);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = EmployeeCategoryRepository::createRec(['name' => 'Operator', 'code' => 'OPR']);
        EmployeeCategoryRepository::createRec(['name' => 'Supervisor', 'code' => 'SUP']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        EmployeeCategoryRepository::updateRec($model->id, [
            'name' => 'Operator',
            'code' => 'SUP',
            'updated_at' => $model->updated_at,
        ]);
    }
}
