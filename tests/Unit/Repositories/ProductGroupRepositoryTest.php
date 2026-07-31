<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\ProductGroupRepository;

class ProductGroupRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = ProductGroupRepository::createRec(['name' => 'Knits', 'code' => 'KNT']);

        $this->assertDatabaseHas('product_groups', ['id' => $model->id, 'code' => 'KNT']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ProductGroupRepository::createRec(['name' => 'Knits']);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        ProductGroupRepository::createRec(['name' => 'Knits', 'code' => 'KNT']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ProductGroupRepository::createRec(['name' => 'Wovens', 'code' => 'KNT']);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = ProductGroupRepository::createRec(['name' => 'Knits', 'code' => 'KNT']);
        ProductGroupRepository::createRec(['name' => 'Wovens', 'code' => 'WVN']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ProductGroupRepository::updateRec($model->id, [
            'name' => 'Knits',
            'code' => 'WVN',
            'updated_at' => $model->updated_at,
        ]);
    }
}
