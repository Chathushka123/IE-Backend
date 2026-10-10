<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class StyleMilestoneTest extends TestCase
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
     * Style is factory-scoped (ScopedToFactories) — Style::whereRaw(...)/whereHas(...)
     * lookups (used by the import's style-name matching and the customer/season/
     * category/factory filters) return nothing unless the authenticated user is linked
     * to the relevant factory(ies) and the request carries a matching X-Factory-Ids
     * header, same as SkillMatrixInsightsTest/GapAnalysisDatasetTest's authHeaders().
     * Plain authHeaders() above is fine for every other endpoint here, which never
     * queries Style directly.
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

    private function authHeadersScopedToStyle(int $styleId): array
    {
        $factoryId = $this->makeFactory();
        DB::table('factory_style')->insert([
            'factory_id' => $factoryId,
            'style_id' => $styleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->authHeadersScopedToFactories([$factoryId]);
    }

    private function makeStyle(): int
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

        return DB::table('styles')->insertGetId([
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

    public function testCreatesAMilestoneRecordForAStyle()
    {
        $styleId = $this->makeStyle();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/styleMilestones', [
            'style_id' => $styleId,
            'planned_quantity' => 1200,
            'planned_cut_date' => '2026-09-01',
            'actual_cut_date' => '2026-09-02',
            'planned_etd' => '2026-10-15',
            'planned_eta' => '2026-11-01',
        ]);

        $response->assertStatus(200)->assertJson([
            'status' => 'success',
            'data' => [
                'style_id' => $styleId,
                'planned_quantity' => 1200,
                'planned_cut_date' => '2026-09-01',
                'actual_cut_date' => '2026-09-02',
                'planned_etd' => '2026-10-15',
                'planned_eta' => '2026-11-01',
            ],
        ]);
        $this->assertDatabaseHas('style_milestones', ['style_id' => $styleId, 'planned_quantity' => 1200]);
    }

    public function testRejectsAZeroOrNegativePlannedQuantity()
    {
        $styleId = $this->makeStyle();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/styleMilestones', [
            'style_id' => $styleId,
            'planned_quantity' => 0,
        ]);

        $response->assertStatus(400);
    }

    public function testRejectsASecondMilestoneRecordForTheSameStyle()
    {
        $styleId = $this->makeStyle();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/styleMilestones', [
            'style_id' => $styleId,
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/styleMilestones', [
            'style_id' => $styleId,
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('already has a milestone record', $response->getContent());
    }

    public function testGetByStyleReturnsNullWhenNoRecordExistsYet()
    {
        $styleId = $this->makeStyle();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/styleMilestones/byStyle/{$styleId}");

        $response->assertStatus(200)->assertJson(['status' => 'success', 'data' => null]);
    }

    public function testUpdatesAnExistingMilestoneRecord()
    {
        $styleId = $this->makeStyle();
        $headers = $this->authHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/styleMilestones', [
            'style_id' => $styleId,
            'planned_cut_date' => '2026-09-01',
        ])->json('data');

        $response = $this->withHeaders($headers)->putJson("/api/v1/styleMilestones/{$created['id']}", [
            'style_id' => $styleId,
            'planned_cut_date' => '2026-09-01',
            'actual_cut_date' => '2026-09-03',
            'updated_at' => $created['updated_at'],
        ]);

        $response->assertStatus(200)->assertJson(['data' => ['actual_cut_date' => '2026-09-03']]);
        $this->assertDatabaseHas('style_milestones', [
            'style_id' => $styleId,
            'actual_cut_date' => '2026-09-03',
        ]);
    }

    public function testRejectsAnUpdateWithAStaleConcurrencyToken()
    {
        $styleId = $this->makeStyle();
        $headers = $this->authHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/styleMilestones', [
            'style_id' => $styleId,
        ])->json('data');

        $response = $this->withHeaders($headers)->putJson("/api/v1/styleMilestones/{$created['id']}", [
            'style_id' => $styleId,
            'actual_cut_date' => '2026-09-03',
            'updated_at' => '2000-01-01 00:00:00',
        ]);

        $response->assertStatus(400);
    }

    public function testRejectsUnauthenticatedRequests()
    {
        $styleId = $this->makeStyle();

        $response = $this->postJson('/api/v1/styleMilestones', ['style_id' => $styleId]);

        $response->assertStatus(401);
    }

    public function testDeletesAMilestoneRecordViaTheGenericMasterDetailsDispatcher()
    {
        $styleId = $this->makeStyle();
        $headers = $this->authHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/styleMilestones', [
            'style_id' => $styleId,
        ])->json('data');

        $response = $this->withHeaders($headers)->postJson('/api/v1/masterDetails', [
            'StyleMilestone' => ['DEL' => [$created['id']]],
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseMissing('style_milestones', ['id' => $created['id']]);
    }

    public function testFiltersByDateRange()
    {
        $styleA = $this->makeStyle();
        $styleB = $this->makeStyle();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/styleMilestones', [
            'style_id' => $styleA,
            'planned_cut_date' => '2026-08-01',
        ])->assertStatus(200);
        $this->withHeaders($headers)->postJson('/api/v1/styleMilestones', [
            'style_id' => $styleB,
            'planned_cut_date' => '2026-09-15',
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/styleMilestones/filter', [
            'planned_cut_date_from' => '2026-09-01',
            'planned_cut_date_to' => '2026-09-30',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($styleB, $response->json('data.0.style_id'));
    }

    public function testFiltersByCustomerSeasonProductCategoryAndFactory()
    {
        $styleA = $this->makeStyle();
        $styleB = $this->makeStyle();

        // Both styles must be within the request's resolved factory scope (Style is
        // factory-scoped — see authHeadersScopedToStyle()) but linked to *different*
        // factories, so the factory_id filter below actually distinguishes them rather
        // than the global scope doing it by accident.
        $factoryA = $this->makeFactory();
        $factoryB = $this->makeFactory();
        DB::table('factory_style')->insert([
            ['factory_id' => $factoryA, 'style_id' => $styleA, 'created_at' => now(), 'updated_at' => now()],
            ['factory_id' => $factoryB, 'style_id' => $styleB, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $headers = $this->authHeadersScopedToFactories([$factoryA, $factoryB]);

        $this->withHeaders($headers)->postJson('/api/v1/styleMilestones', ['style_id' => $styleA])->assertStatus(200);
        $this->withHeaders($headers)->postJson('/api/v1/styleMilestones', ['style_id' => $styleB])->assertStatus(200);

        $styleARow = DB::table('styles')->where('id', $styleA)->first();

        $this->assertFiltersToOnlyStyleA($headers, $styleA, ['customer_id' => [$styleARow->customer_id]]);
        $this->assertFiltersToOnlyStyleA($headers, $styleA, ['season_id' => [$styleARow->season_id]]);
        $this->assertFiltersToOnlyStyleA($headers, $styleA, ['product_category_id' => [$styleARow->product_category_id]]);
        $this->assertFiltersToOnlyStyleA($headers, $styleA, ['factory_id' => [$factoryA]]);
    }

    private function assertFiltersToOnlyStyleA(array $headers, int $styleA, array $filter): void
    {
        $response = $this->withHeaders($headers)->postJson('/api/v1/styleMilestones/filter', array_merge($filter, [
            'page' => 1,
            'per_page' => 20,
        ]));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($styleA, $response->json('data.0.style_id'));
    }

    public function testImportsRowsMatchedByStyleName()
    {
        $styleId = $this->makeStyle();
        $styleName = DB::table('styles')->where('id', $styleId)->value('name');
        $headers = $this->authHeadersScopedToStyle($styleId);

        $response = $this->withHeaders($headers)->postJson('/api/v1/styleMilestones/import', [
            'rows' => [[
                'style' => $styleName,
                'planned_quantity' => '1500',
                'planned_cut_date' => '2026-09-01',
                'planned_etd' => '2026-10-01',
            ]],
        ]);

        $response->assertStatus(200)->assertJson([
            'status' => 'success',
            'data' => ['total' => 1, 'created' => 1, 'updated' => 0, 'failed' => []],
        ]);
        $this->assertDatabaseHas('style_milestones', [
            'style_id' => $styleId,
            'planned_quantity' => 1500,
            'planned_cut_date' => '2026-09-01',
            'planned_etd' => '2026-10-01',
        ]);
    }

    public function testReimportingTheSameStyleUpdatesItsExistingMilestoneRecord()
    {
        $styleId = $this->makeStyle();
        $styleName = DB::table('styles')->where('id', $styleId)->value('name');
        $headers = $this->authHeadersScopedToStyle($styleId);

        $this->withHeaders($headers)->postJson('/api/v1/styleMilestones/import', [
            'rows' => [['style' => $styleName, 'planned_cut_date' => '2026-09-01']],
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/styleMilestones/import', [
            'rows' => [['style' => $styleName, 'planned_cut_date' => '2026-09-05']],
        ]);

        $response->assertStatus(200)->assertJson([
            'data' => ['total' => 1, 'created' => 0, 'updated' => 1],
        ]);
        $this->assertDatabaseHas('style_milestones', ['style_id' => $styleId, 'planned_cut_date' => '2026-09-05']);
        $this->assertEquals(1, DB::table('style_milestones')->where('style_id', $styleId)->count());
    }

    public function testImportFailsCleanlyForAnUnknownStyle()
    {
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->postJson('/api/v1/styleMilestones/import', [
            'rows' => [['style' => 'Does Not Exist', 'planned_cut_date' => '2026-09-01']],
        ]);

        $response->assertStatus(200)->assertJson(['data' => ['total' => 1, 'created' => 0, 'updated' => 0]]);
        $this->assertStringContainsString('not found', $response->json('data.failed.0.error'));
    }
}
