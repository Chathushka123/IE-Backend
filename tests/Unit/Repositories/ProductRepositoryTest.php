<?php

namespace Tests\Unit\Repositories;

use App\Customer;
use App\Exceptions\GeneralException;
use App\Http\Repositories\ProductRepository;
use App\ProductCategory;
use App\ProductGroup;
use App\Season;

class ProductRepositoryTest extends RepositoryTestCase
{
    /** @return array{0:int,1:int,2:int} [product_category_id, customer_id, season_id] */
    private function makeScope(string $suffix = ''): array
    {
        $unique = strtoupper(substr(md5($suffix.uniqid()), 0, 6));
        $groupId = ProductGroup::create(['name' => 'Knits'.$suffix, 'code' => 'KN'.$unique])->id;
        $categoryId = ProductCategory::create(['name' => 'T-Shirts'.$suffix, 'code' => 'TS'.$unique, 'product_group_id' => $groupId])->id;
        $customerId = Customer::create(['description' => 'Decathlon'.$suffix, 'code' => 'DC'.$unique])->id;
        $seasonId = Season::create(['name' => 'Summer 27', 'code' => 'SU'.$unique, 'customer_id' => $customerId])->id;

        return [$categoryId, $customerId, $seasonId];
    }

    public function testCreatesWithAUniqueStyleCodeCombination()
    {
        $this->actingAsTestUser();
        [$categoryId, $customerId, $seasonId] = $this->makeScope();

        $model = ProductRepository::createRec([
            'name' => 'Style 123',
            'style_code' => 'ST123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);

        $this->assertDatabaseHas('products', ['id' => $model->id, 'style_code' => 'ST123']);
    }

    public function testRejectsAMissingStyleCode()
    {
        $this->actingAsTestUser();
        [$categoryId, $customerId, $seasonId] = $this->makeScope();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/style.code/i');

        ProductRepository::createRec([
            'name' => 'Style 123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);
    }

    public function testRejectsAMissingSeason()
    {
        $this->actingAsTestUser();
        [$categoryId, $customerId] = $this->makeScope();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/season/i');

        ProductRepository::createRec([
            'name' => 'Style 123',
            'style_code' => 'ST123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
        ]);
    }

    public function testRejectsADuplicateStyleCodeForTheSameCategoryCustomerAndSeason()
    {
        $this->actingAsTestUser();
        [$categoryId, $customerId, $seasonId] = $this->makeScope();

        ProductRepository::createRec([
            'name' => 'Style 123',
            'style_code' => 'ST123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/style.code/i');

        ProductRepository::createRec([
            'name' => 'Style 123 Restock',
            'style_code' => 'ST123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);
    }

    /** The uniqueness is scoped to (category, customer, season) together, so the same style_code is fine in a different scope. */
    public function testAllowsTheSameStyleCodeUnderADifferentScope()
    {
        $this->actingAsTestUser();
        [$categoryId, $customerId, $seasonId] = $this->makeScope('One');
        [$categoryTwoId, $customerTwoId, $seasonTwoId] = $this->makeScope('Two');

        ProductRepository::createRec([
            'name' => 'Style 123 A',
            'style_code' => 'ST123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);

        $second = ProductRepository::createRec([
            'name' => 'Style 123 B',
            'style_code' => 'ST123',
            'product_category_id' => $categoryTwoId,
            'customer_id' => $customerTwoId,
            'season_id' => $seasonTwoId,
        ]);

        $this->assertDatabaseHas('products', ['id' => $second->id, 'style_code' => 'ST123', 'customer_id' => $customerTwoId]);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherProductsStyleCodeInTheSameScope()
    {
        $this->actingAsTestUser();
        [$categoryId, $customerId, $seasonId] = $this->makeScope();

        $model = ProductRepository::createRec([
            'name' => 'Style 123',
            'style_code' => 'ST123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);
        ProductRepository::createRec([
            'name' => 'Style 456',
            'style_code' => 'ST456',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/style.code/i');

        ProductRepository::updateRec($model->id, [
            'name' => 'Style 123',
            'style_code' => 'ST456',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
            'updated_at' => $model->updated_at,
        ]);
    }
}
