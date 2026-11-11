<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\MachineTypeRepository;
use App\MachineCategory;

class MachineTypeRepositoryTest extends RepositoryTestCase
{
    private function makeCategory(): int
    {
        return MachineCategory::create(['name' => 'Sewing Machines'])->id;
    }

    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();
        $categoryId = $this->makeCategory();

        $model = MachineTypeRepository::createRec([
            'name' => 'Single Needle Lockstitch',
            'code' => 'SNLS',
            'machine_category_id' => $categoryId,
        ]);

        $this->assertDatabaseHas('machine_types', ['id' => $model->id, 'code' => 'SNLS']);
    }

    public function testStoresAStandardRpm()
    {
        $this->actingAsTestUser();
        $categoryId = $this->makeCategory();

        $model = MachineTypeRepository::createRec([
            'name' => 'Single Needle Lockstitch',
            'code' => 'SNLS',
            'machine_category_id' => $categoryId,
            'standard_rpm' => 5000,
        ]);

        $this->assertDatabaseHas('machine_types', ['id' => $model->id, 'standard_rpm' => 5000]);
    }

    public function testStandardRpmIsOptional()
    {
        $this->actingAsTestUser();
        $categoryId = $this->makeCategory();

        $model = MachineTypeRepository::createRec(['name' => 'Overlock', 'code' => 'OVL', 'machine_category_id' => $categoryId]);

        $this->assertDatabaseHas('machine_types', ['id' => $model->id, 'standard_rpm' => null]);
    }

    /**
     * @dataProvider invalidRpmProvider
     */
    public function testRejectsAnInvalidStandardRpm($rpm)
    {
        $this->actingAsTestUser();
        $categoryId = $this->makeCategory();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/standard rpm/i');

        MachineTypeRepository::createRec(['name' => 'Overlock', 'code' => 'OVL', 'machine_category_id' => $categoryId, 'standard_rpm' => $rpm]);
    }

    public function invalidRpmProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-100],
            'fractional' => [4999.5],
            'text' => ['fast'],
            'too large' => [100001],
        ];
    }

    public function testUpdatesAndClearsTheStandardRpm()
    {
        $this->actingAsTestUser();
        $categoryId = $this->makeCategory();
        $model = MachineTypeRepository::createRec(['name' => 'Overlock', 'code' => 'OVL', 'machine_category_id' => $categoryId, 'standard_rpm' => 4000]);

        $updated = MachineTypeRepository::updateRec($model->id, [
            'name' => 'Overlock', 'code' => 'OVL', 'machine_category_id' => $categoryId, 'standard_rpm' => null,
            'updated_at' => $model->fresh()->updated_at->toDateTimeString(),
        ]);

        $this->assertNull($updated->standard_rpm);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();
        $categoryId = $this->makeCategory();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        MachineTypeRepository::createRec([
            'name' => 'Single Needle Lockstitch',
            'machine_category_id' => $categoryId,
        ]);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();
        $categoryId = $this->makeCategory();

        MachineTypeRepository::createRec(['name' => 'Single Needle Lockstitch', 'code' => 'SNLS', 'machine_category_id' => $categoryId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        MachineTypeRepository::createRec(['name' => 'Overlock', 'code' => 'SNLS', 'machine_category_id' => $categoryId]);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();
        $categoryId = $this->makeCategory();

        $model = MachineTypeRepository::createRec(['name' => 'Single Needle Lockstitch', 'code' => 'SNLS', 'machine_category_id' => $categoryId]);
        MachineTypeRepository::createRec(['name' => 'Overlock', 'code' => 'OVL', 'machine_category_id' => $categoryId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        MachineTypeRepository::updateRec($model->id, [
            'name' => 'Single Needle Lockstitch',
            'code' => 'OVL',
            'machine_category_id' => $categoryId,
            'updated_at' => $model->updated_at,
        ]);
    }
}
