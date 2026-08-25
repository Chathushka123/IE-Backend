<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProductMilestoneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fixtures are inserted via the query builder (not Eloquent::create) because
     * the models' boot() creating listener requires an authenticated Auth::user(),
     * which only exists once we're inside an authenticated HTTP request.
     */
    private function authHeaders(): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Planner',
            'email' => 'planner@example.com',
            'password' => bcrypt('Password1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = JWTAuth::fromUser(User::find($userId));

        return ['Authorization' => "Bearer $token"];
    }

    private function makeFactory(): int
    {
        $unique = strtoupper(substr(md5(uniqid()), 0, 6));

        return DB::table('factories')->insertGetId([
            'name' => 'Milestone Test Factory'.$unique,
            'code' => 'MTF'.$unique,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Product is factory-scoped (ScopedToFactories) — Product::whereRaw(...)/whereHas(...)
     * lookups (used by the import's product-name matching and the customer/season/
     * category/factory filters) return nothing unless the authenticated user is linked
     * to the relevant factory(ies) and the request carries a matching X-Factory-Ids
     * header, same as SkillMatrixInsightsTest/GapAnalysisDatasetTest's authHeaders().
     * Plain authHeaders() above is fine for every other endpoint here, which never
     * queries Product directly.
     */
    private function authHeadersScopedToFactories(array $factoryIds): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Milestone Importer',
            'email' => 'milestone-importer-'.uniqid().'@example.com',
            'password' => bcrypt('Password1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('factory_user')->insert(array_map(
            fn ($factoryId) => ['factory_id' => $factoryId, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()],
            $factoryIds
        ));

        $token = JWTAuth::fromUser(User::find($userId));

        return [
            'Authorization' => "Bearer $token",
            'X-Factory-Ids' => implode(',', $factoryIds),
        ];
    }

    private function authHeadersScopedToProduct(int $productId): array
    {
        $factoryId = $this->makeFactory();
        DB::table('factory_product')->insert([
            'factory_id' => $factoryId,
            'product_id' => $productId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->authHeadersScopedToFactories([$factoryId]);
    }

    private function makeProduct(): int
    {
        $unique = strtoupper(substr(md5(uniqid()), 0, 6));

        $groupId = DB::table('product_groups')->insertGetId([
            'name' => 'Knits'.$unique,
            'code' => 'KN'.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'T-Shirts'.$unique,
            'code' => 'TS'.$unique,
            'product_group_id' => $groupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $customerId = DB::table('customers')->insertGetId([
            'description' => 'Decathlon'.$unique,
            'code' => 'DC'.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $seasonId = DB::table('seasons')->insertGetId([
            'name' => 'Summer 27',
            'code' => 'SU'.$unique,
            'customer_id' => $customerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('products')->insertGetId([
            'name' => 'Style 123'.$unique,
            'style_code' => 'ST'.$unique,
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function testCreatesAMilestoneRecordForAProduct()
    {
        $productId = $this->makeProduct();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/productMilestones', [
            'product_id' => $productId,
            'planned_quantity' => 1200,
            'planned_cut_date' => '2026-09-01',
            'actual_cut_date' => '2026-09-02',
            'planned_etd' => '2026-10-15',
            'planned_eta' => '2026-11-01',
        ]);

        $response->assertStatus(200)->assertJson([
            'status' => 'success',
            'data' => [
                'product_id' => $productId,
                'planned_quantity' => 1200,
                'planned_cut_date' => '2026-09-01',
                'actual_cut_date' => '2026-09-02',
                'planned_etd' => '2026-10-15',
                'planned_eta' => '2026-11-01',
            ],
        ]);
        $this->assertDatabaseHas('product_milestones', ['product_id' => $productId, 'planned_quantity' => 1200]);
    }

    public function testRejectsAZeroOrNegativePlannedQuantity()
    {
        $productId = $this->makeProduct();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/productMilestones', [
            'product_id' => $productId,
            'planned_quantity' => 0,
        ]);

        $response->assertStatus(400);
    }

    public function testRejectsASecondMilestoneRecordForTheSameProduct()
    {
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/productMilestones', [
            'product_id' => $productId,
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/productMilestones', [
            'product_id' => $productId,
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('already has a milestone record', $response->getContent());
    }

    public function testGetByProductReturnsNullWhenNoRecordExistsYet()
    {
        $productId = $this->makeProduct();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/productMilestones/byProduct/{$productId}");

        $response->assertStatus(200)->assertJson(['status' => 'success', 'data' => null]);
    }

    public function testUpdatesAnExistingMilestoneRecord()
    {
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/productMilestones', [
            'product_id' => $productId,
            'planned_cut_date' => '2026-09-01',
        ])->json('data');

        $response = $this->withHeaders($headers)->putJson("/api/v1/productMilestones/{$created['id']}", [
            'product_id' => $productId,
            'planned_cut_date' => '2026-09-01',
            'actual_cut_date' => '2026-09-03',
            'updated_at' => $created['updated_at'],
        ]);

        $response->assertStatus(200)->assertJson(['data' => ['actual_cut_date' => '2026-09-03']]);
        $this->assertDatabaseHas('product_milestones', [
            'product_id' => $productId,
            'actual_cut_date' => '2026-09-03',
        ]);
    }

    public function testRejectsAnUpdateWithAStaleConcurrencyToken()
    {
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/productMilestones', [
            'product_id' => $productId,
        ])->json('data');

        $response = $this->withHeaders($headers)->putJson("/api/v1/productMilestones/{$created['id']}", [
            'product_id' => $productId,
            'actual_cut_date' => '2026-09-03',
            'updated_at' => '2000-01-01 00:00:00',
        ]);

        $response->assertStatus(400);
    }

    public function testRejectsUnauthenticatedRequests()
    {
        $productId = $this->makeProduct();

        $response = $this->postJson('/api/v1/productMilestones', ['product_id' => $productId]);

        $response->assertStatus(401);
    }

    public function testDeletesAMilestoneRecordViaTheGenericMasterDetailsDispatcher()
    {
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/productMilestones', [
            'product_id' => $productId,
        ])->json('data');

        $response = $this->withHeaders($headers)->postJson('/api/v1/masterDetails', [
            'ProductMilestone' => ['DEL' => [$created['id']]],
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseMissing('product_milestones', ['id' => $created['id']]);
    }

    public function testFiltersByDateRange()
    {
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/productMilestones', [
            'product_id' => $productA,
            'planned_cut_date' => '2026-08-01',
        ])->assertStatus(200);
        $this->withHeaders($headers)->postJson('/api/v1/productMilestones', [
            'product_id' => $productB,
            'planned_cut_date' => '2026-09-15',
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/productMilestones/filter', [
            'planned_cut_date_from' => '2026-09-01',
            'planned_cut_date_to' => '2026-09-30',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($productB, $response->json('data.0.product_id'));
    }

    public function testFiltersByCustomerSeasonProductCategoryAndFactory()
    {
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();

        // Both products must be within the request's resolved factory scope (Product is
        // factory-scoped — see authHeadersScopedToProduct()) but linked to *different*
        // factories, so the factory_id filter below actually distinguishes them rather
        // than the global scope doing it by accident.
        $factoryA = $this->makeFactory();
        $factoryB = $this->makeFactory();
        DB::table('factory_product')->insert([
            ['factory_id' => $factoryA, 'product_id' => $productA, 'created_at' => now(), 'updated_at' => now()],
            ['factory_id' => $factoryB, 'product_id' => $productB, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $headers = $this->authHeadersScopedToFactories([$factoryA, $factoryB]);

        $this->withHeaders($headers)->postJson('/api/v1/productMilestones', ['product_id' => $productA])->assertStatus(200);
        $this->withHeaders($headers)->postJson('/api/v1/productMilestones', ['product_id' => $productB])->assertStatus(200);

        $productARow = DB::table('products')->where('id', $productA)->first();

        $this->assertFiltersToOnlyProductA($headers, $productA, ['customer_id' => [$productARow->customer_id]]);
        $this->assertFiltersToOnlyProductA($headers, $productA, ['season_id' => [$productARow->season_id]]);
        $this->assertFiltersToOnlyProductA($headers, $productA, ['product_category_id' => [$productARow->product_category_id]]);
        $this->assertFiltersToOnlyProductA($headers, $productA, ['factory_id' => [$factoryA]]);
    }

    private function assertFiltersToOnlyProductA(array $headers, int $productA, array $filter): void
    {
        $response = $this->withHeaders($headers)->postJson('/api/v1/productMilestones/filter', array_merge($filter, [
            'page' => 1,
            'per_page' => 20,
        ]));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($productA, $response->json('data.0.product_id'));
    }

    public function testImportsRowsMatchedByProductName()
    {
        $productId = $this->makeProduct();
        $productName = DB::table('products')->where('id', $productId)->value('name');
        $headers = $this->authHeadersScopedToProduct($productId);

        $response = $this->withHeaders($headers)->postJson('/api/v1/productMilestones/import', [
            'rows' => [[
                'product' => $productName,
                'planned_quantity' => '1500',
                'planned_cut_date' => '2026-09-01',
                'planned_etd' => '2026-10-01',
            ]],
        ]);

        $response->assertStatus(200)->assertJson([
            'status' => 'success',
            'data' => ['total' => 1, 'created' => 1, 'updated' => 0, 'failed' => []],
        ]);
        $this->assertDatabaseHas('product_milestones', [
            'product_id' => $productId,
            'planned_quantity' => 1500,
            'planned_cut_date' => '2026-09-01',
            'planned_etd' => '2026-10-01',
        ]);
    }

    public function testReimportingTheSameProductUpdatesItsExistingMilestoneRecord()
    {
        $productId = $this->makeProduct();
        $productName = DB::table('products')->where('id', $productId)->value('name');
        $headers = $this->authHeadersScopedToProduct($productId);

        $this->withHeaders($headers)->postJson('/api/v1/productMilestones/import', [
            'rows' => [['product' => $productName, 'planned_cut_date' => '2026-09-01']],
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/productMilestones/import', [
            'rows' => [['product' => $productName, 'planned_cut_date' => '2026-09-05']],
        ]);

        $response->assertStatus(200)->assertJson([
            'data' => ['total' => 1, 'created' => 0, 'updated' => 1],
        ]);
        $this->assertDatabaseHas('product_milestones', ['product_id' => $productId, 'planned_cut_date' => '2026-09-05']);
        $this->assertEquals(1, DB::table('product_milestones')->where('product_id', $productId)->count());
    }

    public function testImportFailsCleanlyForAnUnknownProduct()
    {
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->postJson('/api/v1/productMilestones/import', [
            'rows' => [['product' => 'Does Not Exist', 'planned_cut_date' => '2026-09-01']],
        ]);

        $response->assertStatus(200)->assertJson(['data' => ['total' => 1, 'created' => 0, 'updated' => 0]]);
        $this->assertStringContainsString('not found', $response->json('data.failed.0.error'));
    }
}
