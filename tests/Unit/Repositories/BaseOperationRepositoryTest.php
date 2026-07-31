<?php

namespace Tests\Unit\Repositories;

use App\BaseOperationCategory;
use App\Exceptions\GeneralException;
use App\Http\Repositories\BaseOperationRepository;

class BaseOperationRepositoryTest extends RepositoryTestCase
{
    private function makeCategory(): int
    {
        return BaseOperationCategory::create(['name' => 'Sewing Operations'])->id;
    }

    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();
        $categoryId = $this->makeCategory();

        $model = BaseOperationRepository::createRec([
            'name' => 'Stitch Sleeve',
            'code' => 'STS',
            'base_operation_category_id' => $categoryId,
        ]);

        $this->assertDatabaseHas('base_operations', ['id' => $model->id, 'code' => 'STS']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();
        $categoryId = $this->makeCategory();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        BaseOperationRepository::createRec([
            'name' => 'Stitch Sleeve',
            'base_operation_category_id' => $categoryId,
        ]);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();
        $categoryId = $this->makeCategory();

        BaseOperationRepository::createRec(['name' => 'Stitch Sleeve', 'code' => 'STS', 'base_operation_category_id' => $categoryId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        BaseOperationRepository::createRec(['name' => 'Attach Collar', 'code' => 'STS', 'base_operation_category_id' => $categoryId]);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();
        $categoryId = $this->makeCategory();

        $model = BaseOperationRepository::createRec(['name' => 'Stitch Sleeve', 'code' => 'STS', 'base_operation_category_id' => $categoryId]);
        BaseOperationRepository::createRec(['name' => 'Attach Collar', 'code' => 'ATC', 'base_operation_category_id' => $categoryId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        BaseOperationRepository::updateRec($model->id, [
            'name' => 'Stitch Sleeve',
            'code' => 'ATC',
            'base_operation_category_id' => $categoryId,
            'updated_at' => $model->updated_at,
        ]);
    }
}
