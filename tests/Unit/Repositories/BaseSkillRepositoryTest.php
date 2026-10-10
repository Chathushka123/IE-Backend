<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\SoftSkillRepository;

class SoftSkillRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = SoftSkillRepository::createRec(['name' => 'Teamwork', 'code' => 'TWK']);

        $this->assertDatabaseHas('soft_skills', ['id' => $model->id, 'code' => 'TWK']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        SoftSkillRepository::createRec(['name' => 'Teamwork']);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        SoftSkillRepository::createRec(['name' => 'Teamwork', 'code' => 'TWK']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        SoftSkillRepository::createRec(['name' => 'Communication', 'code' => 'TWK']);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = SoftSkillRepository::createRec(['name' => 'Teamwork', 'code' => 'TWK']);
        SoftSkillRepository::createRec(['name' => 'Communication', 'code' => 'COM']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        SoftSkillRepository::updateRec($model->id, [
            'name' => 'Teamwork',
            'code' => 'COM',
            'updated_at' => $model->updated_at,
        ]);
    }
}
