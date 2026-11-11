<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\ConcurrencyCheckFailedException;
use App\Exceptions\GeneralException;
use App\Http\Repositories\ProductFamilyRepository;
use App\ProductFamily;
use App\ProductGroup;

class ProductFamilyRepositoryTest extends RepositoryTestCase
{
    private function makeProductGroup(string $name = 'Tops', string $code = 'TOP'): int
    {
        return ProductGroup::create(['name' => $name, 'code' => $code])->id;
    }

    public function testCreatesUnderAGroup()
    {
        $this->actingAsTestUser();
        $groupId = $this->makeProductGroup();

        $model = ProductFamilyRepository::createRec(['name' => 'Knit Tops', 'code' => 'KT', 'product_group_id' => $groupId]);

        $this->assertDatabaseHas('product_families', ['id' => $model->id, 'code' => 'KT', 'product_group_id' => $groupId]);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();
        $groupId = $this->makeProductGroup();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ProductFamilyRepository::createRec(['name' => 'Knit Tops', 'product_group_id' => $groupId]);
    }

    public function testRejectsAMissingGroup()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/product group/i');

        ProductFamilyRepository::createRec(['name' => 'Knit Tops', 'code' => 'KT']);
    }

    public function testRejectsAGroupThatDoesNotExist()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/product group/i');

        ProductFamilyRepository::createRec(['name' => 'Knit Tops', 'code' => 'KT', 'product_group_id' => 999999]);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();
        $groupId = $this->makeProductGroup();
        ProductFamilyRepository::createRec(['name' => 'Knit Tops', 'code' => 'KT', 'product_group_id' => $groupId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ProductFamilyRepository::createRec(['name' => 'Woven Tops', 'code' => 'KT', 'product_group_id' => $groupId]);
    }

    public function testRejectsADuplicateNameOnCreate()
    {
        $this->actingAsTestUser();
        $groupId = $this->makeProductGroup();
        ProductFamilyRepository::createRec(['name' => 'Knit Tops', 'code' => 'KT', 'product_group_id' => $groupId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/name/i');

        ProductFamilyRepository::createRec(['name' => 'Knit Tops', 'code' => 'KT2', 'product_group_id' => $groupId]);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();
        $groupId = $this->makeProductGroup();
        $model = ProductFamilyRepository::createRec(['name' => 'Knit Tops', 'code' => 'KT', 'product_group_id' => $groupId]);
        ProductFamilyRepository::createRec(['name' => 'Woven Tops', 'code' => 'WT', 'product_group_id' => $groupId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        ProductFamilyRepository::updateRec($model->id, [
            'name' => 'Knit Tops',
            'code' => 'WT',
            'product_group_id' => $groupId,
            'updated_at' => $model->updated_at,
        ]);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsName()
    {
        $this->actingAsTestUser();
        $groupId = $this->makeProductGroup();
        $model = ProductFamilyRepository::createRec(['name' => 'Knit Tops', 'code' => 'KT', 'product_group_id' => $groupId]);
        ProductFamilyRepository::createRec(['name' => 'Woven Tops', 'code' => 'WT', 'product_group_id' => $groupId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/name/i');

        ProductFamilyRepository::updateRec($model->id, [
            'name' => 'Woven Tops',
            'code' => 'KT',
            'product_group_id' => $groupId,
            'updated_at' => $model->updated_at,
        ]);
    }

    public function testUpdateCanMoveAFamilyToAnotherGroup()
    {
        $this->actingAsTestUser();
        $tops = $this->makeProductGroup();
        $bottoms = $this->makeProductGroup('Bottoms', 'BOT');
        $model = ProductFamilyRepository::createRec(['name' => 'Knit Tops', 'code' => 'KT', 'product_group_id' => $tops]);

        ProductFamilyRepository::updateRec($model->id, [
            'name' => 'Knit Tops',
            'code' => 'KT',
            'product_group_id' => $bottoms,
            'updated_at' => $model->updated_at,
        ]);

        $this->assertDatabaseHas('product_families', ['id' => $model->id, 'product_group_id' => $bottoms]);
    }

    public function testRejectsAnUpdateOfAStaleRecord()
    {
        $this->actingAsTestUser();
        $groupId = $this->makeProductGroup();
        $model = ProductFamilyRepository::createRec(['name' => 'Knit Tops', 'code' => 'KT', 'product_group_id' => $groupId]);

        $this->expectException(ConcurrencyCheckFailedException::class);

        ProductFamilyRepository::updateRec($model->id, [
            'name' => 'Knit Tops',
            'code' => 'KT',
            'product_group_id' => $groupId,
            'updated_at' => $model->updated_at->copy()->subDay()->toDateTimeString(),
        ]);
    }

    public function testDeletesRecords()
    {
        $this->actingAsTestUser();
        $groupId = $this->makeProductGroup();
        $model = ProductFamilyRepository::createRec(['name' => 'Knit Tops', 'code' => 'KT', 'product_group_id' => $groupId]);

        ProductFamilyRepository::deleteRecs([$model->id]);

        $this->assertNull(ProductFamily::find($model->id));
    }
}
