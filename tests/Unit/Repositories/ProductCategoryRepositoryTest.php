<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\ProductCategoryRepository;
use App\ProductGroup;

class ProductCategoryRepositoryTest extends RepositoryTestCase
{
    private function makeProductGroup(): int
    {
        return ProductGroup::create(['name' => 'Knits', 'code' => 'KNT'])->id;
    }

    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();
        $groupId = $this->makeProductGroup();

        $model = ProductCategoryRepository::createRec([
            'name' => 'T-Shirts',
            'code' => 'TSH',
            'product_group_id' => $groupId,
        ]);

        $this->assertDatabaseHas('product_categories', ['id' => $model->id, 'code' => 'TSH']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();
        $groupId = $this->makeProductGroup();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ProductCategoryRepository::createRec([
            'name' => 'T-Shirts',
            'product_group_id' => $groupId,
        ]);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();
        $groupId = $this->makeProductGroup();

        ProductCategoryRepository::createRec(['name' => 'T-Shirts', 'code' => 'TSH', 'product_group_id' => $groupId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ProductCategoryRepository::createRec(['name' => 'Polos', 'code' => 'TSH', 'product_group_id' => $groupId]);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();
        $groupId = $this->makeProductGroup();

        $model = ProductCategoryRepository::createRec(['name' => 'T-Shirts', 'code' => 'TSH', 'product_group_id' => $groupId]);
        ProductCategoryRepository::createRec(['name' => 'Polos', 'code' => 'PLO', 'product_group_id' => $groupId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ProductCategoryRepository::updateRec($model->id, [
            'name' => 'T-Shirts',
            'code' => 'PLO',
            'product_group_id' => $groupId,
            'updated_at' => $model->updated_at,
        ]);
    }
}
