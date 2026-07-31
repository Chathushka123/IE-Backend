<?php

namespace Tests\Unit\Repositories;

use App\Country;
use App\Department;
use App\Exceptions\GeneralException;
use App\Factory;
use App\Http\Repositories\TeamRepository;
use App\Region;
use App\Section;

class TeamRepositoryTest extends RepositoryTestCase
{
    private function makeFactory(string $name = 'Plant 1'): int
    {
        $unique = strtoupper(substr(md5($name.uniqid()), 0, 6));
        $countryId = Country::create(['name' => 'Sri Lanka '.$name, 'code' => 'LK'.$unique])->id;
        $regionId = Region::create(['name' => 'Western '.$name, 'country_id' => $countryId])->id;

        return Factory::create(['name' => $name, 'code' => 'FC'.$unique, 'country_id' => $countryId, 'region_id' => $regionId])->id;
    }

    private function makeSectionAndDepartment(): array
    {
        return [
            Section::create(['name' => 'Sewing', 'code' => 'SEW'])->id,
            Department::create(['name' => 'Production', 'code' => 'PRD'])->id,
        ];
    }

    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();
        $factoryId = $this->makeFactory();
        [$sectionId, $departmentId] = $this->makeSectionAndDepartment();

        $model = TeamRepository::createRec([
            'name' => 'Line A',
            'code' => 'LA',
            'section_id' => $sectionId,
            'department_id' => $departmentId,
            'factory_id' => $factoryId,
        ]);

        $this->assertDatabaseHas('teams', ['id' => $model->id, 'code' => 'LA']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();
        $factoryId = $this->makeFactory();
        [$sectionId, $departmentId] = $this->makeSectionAndDepartment();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        TeamRepository::createRec([
            'name' => 'Line A',
            'section_id' => $sectionId,
            'department_id' => $departmentId,
            'factory_id' => $factoryId,
        ]);
    }

    public function testRejectsADuplicateCodeForTheSameFactory()
    {
        $this->actingAsTestUser();
        $factoryId = $this->makeFactory();
        [$sectionId, $departmentId] = $this->makeSectionAndDepartment();

        TeamRepository::createRec(['name' => 'Line A', 'code' => 'LA', 'section_id' => $sectionId, 'department_id' => $departmentId, 'factory_id' => $factoryId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        TeamRepository::createRec(['name' => 'Line A2', 'code' => 'LA', 'section_id' => $sectionId, 'department_id' => $departmentId, 'factory_id' => $factoryId]);
    }

    /** The uniqueness is scoped per factory, so a different factory can reuse the same team code. */
    public function testAllowsTheSameCodeForADifferentFactory()
    {
        $this->actingAsTestUser();
        $factoryOneId = $this->makeFactory('Plant 1');
        $factoryTwoId = $this->makeFactory('Plant 2');
        [$sectionId, $departmentId] = $this->makeSectionAndDepartment();

        TeamRepository::createRec(['name' => 'Line A - Plant 1', 'code' => 'LA', 'section_id' => $sectionId, 'department_id' => $departmentId, 'factory_id' => $factoryOneId]);
        $second = TeamRepository::createRec(['name' => 'Line A - Plant 2', 'code' => 'LA', 'section_id' => $sectionId, 'department_id' => $departmentId, 'factory_id' => $factoryTwoId]);

        $this->assertDatabaseHas('teams', ['id' => $second->id, 'code' => 'LA', 'factory_id' => $factoryTwoId]);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherTeamsCodeForTheSameFactory()
    {
        $this->actingAsTestUser();
        $factoryId = $this->makeFactory();
        [$sectionId, $departmentId] = $this->makeSectionAndDepartment();

        $model = TeamRepository::createRec(['name' => 'Line A', 'code' => 'LA', 'section_id' => $sectionId, 'department_id' => $departmentId, 'factory_id' => $factoryId]);
        TeamRepository::createRec(['name' => 'Line B', 'code' => 'LB', 'section_id' => $sectionId, 'department_id' => $departmentId, 'factory_id' => $factoryId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        TeamRepository::updateRec($model->id, [
            'name' => 'Line A',
            'code' => 'LB',
            'section_id' => $sectionId,
            'department_id' => $departmentId,
            'factory_id' => $factoryId,
            'updated_at' => $model->updated_at,
        ]);
    }
}
