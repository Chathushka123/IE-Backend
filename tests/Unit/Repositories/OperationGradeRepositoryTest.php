<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\OperationGradeRepository;

class OperationGradeRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = OperationGradeRepository::createRec(['name' => 'Grade A', 'code' => 'GA']);

        $this->assertDatabaseHas('operation_grades', ['id' => $model->id, 'code' => 'GA']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        OperationGradeRepository::createRec(['name' => 'Grade A']);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        OperationGradeRepository::createRec(['name' => 'Grade A', 'code' => 'GA']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        OperationGradeRepository::createRec(['name' => 'Grade B', 'code' => 'GA']);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = OperationGradeRepository::createRec(['name' => 'Grade A', 'code' => 'GA']);
        OperationGradeRepository::createRec(['name' => 'Grade B', 'code' => 'GB']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        OperationGradeRepository::updateRec($model->id, [
            'name' => 'Grade A',
            'code' => 'GB',
            'updated_at' => $model->updated_at,
        ]);
    }
}
