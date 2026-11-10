<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\ProductCategoryRepository;
use App\ProductFamily;
use App\ProductGroup;

class ProductCategoryRepositoryTest extends RepositoryTestCase
{
    /** A category belongs to a family, which belongs to a group; returns the family id. */
    private function makeProductFamily(): int
    {
        $groupId = ProductGroup::create(['name' => 'Knits', 'code' => 'KNT'])->id;

        return ProductFamily::create(['name' => 'Tees', 'code' => 'TEE', 'product_group_id' => $groupId])->id;
    }

    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();
        $familyId = $this->makeProductFamily();

        $model = ProductCategoryRepository::createRec([
            'name' => 'T-Shirts',
            'code' => 'TSH',
            'product_family_id' => $familyId,
        ]);

        $this->assertDatabaseHas('product_categories', ['id' => $model->id, 'code' => 'TSH']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();
        $familyId = $this->makeProductFamily();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ProductCategoryRepository::createRec([
            'name' => 'T-Shirts',
            'product_family_id' => $familyId,
        ]);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();
        $familyId = $this->makeProductFamily();

        ProductCategoryRepository::createRec(['name' => 'T-Shirts', 'code' => 'TSH', 'product_family_id' => $familyId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ProductCategoryRepository::createRec(['name' => 'Polos', 'code' => 'TSH', 'product_family_id' => $familyId]);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();
        $familyId = $this->makeProductFamily();

        $model = ProductCategoryRepository::createRec(['name' => 'T-Shirts', 'code' => 'TSH', 'product_family_id' => $familyId]);
        ProductCategoryRepository::createRec(['name' => 'Polos', 'code' => 'PLO', 'product_family_id' => $familyId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ProductCategoryRepository::updateRec($model->id, [
            'name' => 'T-Shirts',
            'code' => 'PLO',
            'product_family_id' => $familyId,
            'updated_at' => $model->updated_at,
        ]);
    }

    public function testRejectsAMissingFamily()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/product family/i');

        ProductCategoryRepository::createRec(['name' => 'T-Shirts', 'code' => 'TSH']);
    }

    public function testRejectsAFamilyThatDoesNotExist()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/product family/i');

        ProductCategoryRepository::createRec(['name' => 'T-Shirts', 'code' => 'TSH', 'product_family_id' => 999999]);
    }

    public function testTheCategoryReachesItsGroupThroughItsFamily()
    {
        $this->actingAsTestUser();
        $familyId = $this->makeProductFamily();

        $model = ProductCategoryRepository::createRec(['name' => 'T-Shirts', 'code' => 'TSH', 'product_family_id' => $familyId]);

        $this->assertSame('Knits', $model->fresh()->productFamily->productGroup->name);
    }
}
