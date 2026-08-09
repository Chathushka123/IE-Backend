<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\ManagementHierarchyRepository;

class ManagementHierarchyRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = ManagementHierarchyRepository::createRec(['name' => 'Operator', 'code' => 'OPR', 'seq_no' => 1]);

        $this->assertDatabaseHas('management_hierarchies', ['id' => $model->id, 'code' => 'OPR', 'seq_no' => 1]);
    }

    /** code is intentionally optional here, unlike the other tables in this batch. */
    public function testAllowsMultipleRecordsWithNoCode()
    {
        $this->actingAsTestUser();

        $first = ManagementHierarchyRepository::createRec(['name' => 'Operator', 'seq_no' => 1]);
        $second = ManagementHierarchyRepository::createRec(['name' => 'Supervisor', 'seq_no' => 2]);

        $this->assertDatabaseHas('management_hierarchies', ['id' => $first->id, 'code' => null]);
        $this->assertDatabaseHas('management_hierarchies', ['id' => $second->id, 'code' => null]);
    }

    /** seq_no is mandatory — it drives display ordering for tables/dropdowns on the frontend. */
    public function testRejectsAMissingSeqNoOnCreate()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/seq no/i');

        ManagementHierarchyRepository::createRec(['name' => 'Operator']);
    }

    public function testAllowsDuplicateSeqNos()
    {
        $this->actingAsTestUser();

        $first = ManagementHierarchyRepository::createRec(['name' => 'Operator', 'seq_no' => 1]);
        $second = ManagementHierarchyRepository::createRec(['name' => 'Supervisor', 'seq_no' => 1]);

        $this->assertDatabaseHas('management_hierarchies', ['id' => $first->id, 'seq_no' => 1]);
        $this->assertDatabaseHas('management_hierarchies', ['id' => $second->id, 'seq_no' => 1]);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        ManagementHierarchyRepository::createRec(['name' => 'Operator', 'code' => 'OPR', 'seq_no' => 1]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ManagementHierarchyRepository::createRec(['name' => 'Supervisor', 'code' => 'OPR', 'seq_no' => 2]);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = ManagementHierarchyRepository::createRec(['name' => 'Operator', 'code' => 'OPR', 'seq_no' => 1]);
        ManagementHierarchyRepository::createRec(['name' => 'Supervisor', 'code' => 'SUP', 'seq_no' => 2]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ManagementHierarchyRepository::updateRec($model->id, [
            'name' => 'Operator',
            'code' => 'SUP',
            'seq_no' => 1,
            'updated_at' => $model->updated_at,
        ]);
    }
}
