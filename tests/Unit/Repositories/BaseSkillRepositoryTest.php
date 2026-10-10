<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\BaseSkillRepository;

class BaseSkillRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = BaseSkillRepository::createRec(['name' => 'Teamwork', 'code' => 'TWK']);

        $this->assertDatabaseHas('base_skills', ['id' => $model->id, 'code' => 'TWK']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        BaseSkillRepository::createRec(['name' => 'Teamwork']);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        BaseSkillRepository::createRec(['name' => 'Teamwork', 'code' => 'TWK']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        BaseSkillRepository::createRec(['name' => 'Communication', 'code' => 'TWK']);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = BaseSkillRepository::createRec(['name' => 'Teamwork', 'code' => 'TWK']);
        BaseSkillRepository::createRec(['name' => 'Communication', 'code' => 'COM']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        BaseSkillRepository::updateRec($model->id, [
            'name' => 'Teamwork',
            'code' => 'COM',
            'updated_at' => $model->updated_at,
        ]);
    }
}
