<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Support\ProductFamilies;
use Tests\TestCase;

class StyleDuplicateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{styleId:int,headers:array,rec:array,factoryId:int} */
    private function makeStyle(): array
    {
        $u = strtoupper(substr(md5(uniqid()), 0, 6));
        $now = ['created_at' => now(), 'updated_at' => now()];

        $groupId = DB::table('product_groups')->insertGetId(['name' => 'G'.$u, 'code' => 'G'.$u] + $now);
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'C'.$u, 'code' => 'C'.$u, 'product_family_id' => ProductFamilies::forGroup($groupId)] + $now);
        $customerId = DB::table('customers')->insertGetId(['description' => 'Cu'.$u, 'code' => 'CU'.$u] + $now);
        $seasonId = DB::table('seasons')->insertGetId(['name' => 'Summer', 'code' => 'SU'.$u, 'customer_id' => $customerId] + $now);
        $factoryId = DB::table('factories')->insertGetId(['name' => 'F'.$u, 'code' => 'F'.$u, 'is_active' => true] + $now);

        $rec = ['name' => 'Style '.$u, 'style_code' => 'ST'.$u, 'product_category_id' => $categoryId, 'customer_id' => $customerId, 'season_id' => $seasonId];
        $styleId = DB::table('styles')->insertGetId($rec + ['is_active' => true] + $now);
        DB::table('factory_style')->insert(['factory_id' => $factoryId, 'style_id' => $styleId] + $now);

        $userId = DB::table('users')->insertGetId([
            'name' => 'Dup Tester', 'email' => 'dup-'.uniqid().'@example.com', 'password' => bcrypt('Password1'),
            'is_active' => true, 'common_user_state' => 0,
        ] + $now);
        DB::table('factory_user')->insert(['factory_id' => $factoryId, 'user_id' => $userId] + $now);

        return [
            'styleId' => $styleId,
            'factoryId' => $factoryId,
            'rec' => $rec,
            'headers' => [
                'Authorization' => 'Bearer '.JWTAuth::fromUser(User::withoutGlobalScopes()->find($userId)),
                'X-Factory-Ids' => (string) $factoryId,
            ],
        ];
    }

    public function testDuplicatesAStyleAndKeepsItInTheSameFactoryByDefault()
    {
        $s = $this->makeStyle();

        $response = $this->withHeaders($s['headers'])->postJson("/api/v1/styles/{$s['styleId']}/duplicate", array_merge($s['rec'], [
            'name' => 'Copy of it',
            'style_code' => 'COPY1',
        ]));

        $response->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('data.name', 'Copy of it')->assertJsonPath('meta.operations_copied', 0);
        $this->assertDatabaseHas('factory_style', ['style_id' => $response->json('data.id'), 'factory_id' => $s['factoryId']]);
    }

    public function testRejectsAnUnchangedUniqueKeyWithAHelpfulMessage()
    {
        $s = $this->makeStyle();

        $this->withHeaders($s['headers'])->postJson("/api/v1/styles/{$s['styleId']}/duplicate", array_merge($s['rec'], ['name' => 'Copy of it']))
            ->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertSee('Change at least one of Style Code, Product Silhouette, Customer or Season to duplicate this style.', false);

        $this->assertSame(1, DB::table('styles')->count());
    }

    public function testRefusesAStyleOutsideTheUsersFactories()
    {
        $s = $this->makeStyle();
        $other = $this->makeStyle();

        $this->withHeaders($other['headers'])->postJson("/api/v1/styles/{$s['styleId']}/duplicate", array_merge($s['rec'], ['name' => 'Nope', 'style_code' => 'NOPE1']))
            ->assertStatus(400);

        $this->assertSame(2, DB::table('styles')->count());
    }

    public function testCopiesTheChosenBulletinOverHttp()
    {
        $s = $this->makeStyle();
        $now = ['created_at' => now(), 'updated_at' => now()];
        $baseCategoryId = DB::table('base_operation_categories')->insertGetId(['name' => 'BC'.uniqid()] + $now);
        $gradeId = DB::table('operation_grades')->insertGetId(['name' => 'G'.uniqid(), 'code' => 'OG'.substr(uniqid(), -6)] + $now);
        $machineCategoryId = DB::table('machine_categories')->insertGetId(['name' => 'MC'.uniqid()] + $now);
        $machineTypeId = DB::table('machine_types')->insertGetId(['name' => 'MT'.uniqid(), 'code' => 'MT'.substr(uniqid(), -6), 'machine_category_id' => $machineCategoryId] + $now);
        $baseOperationId = DB::table('base_operations')->insertGetId(['name' => 'BO'.uniqid(), 'code' => 'BO'.substr(uniqid(), -6), 'base_operation_category_id' => $baseCategoryId] + $now);
        $operationId = DB::table('operations')->insertGetId([
            'code' => 'OP'.substr(uniqid(), -6), 'description' => 'Op', 'base_operation_id' => $baseOperationId, 'grade_id' => $gradeId,
            'product_category_id' => $s['rec']['product_category_id'], 'machine_type_id' => $machineTypeId, 'smv' => 0.5, 'is_active' => true,
        ] + $now);

        $response = $this->withHeaders($s['headers'])->postJson("/api/v1/styles/{$s['styleId']}/duplicate", array_merge($s['rec'], [
            'name' => 'Copy with ops',
            'style_code' => 'COPY2',
            'operations' => [['operation_id' => $operationId, 'smv' => 0.6, 'is_active' => true]],
        ]));

        $response->assertOk()->assertJsonPath('meta.operations_copied', 1);
        $this->assertDatabaseHas('style_operations', ['style_id' => $response->json('data.id'), 'operation_id' => $operationId, 'sequence_no' => 1]);
    }

    public function testRollsBackTheStyleWhenAnOperationIsRefused()
    {
        $s = $this->makeStyle();

        $this->withHeaders($s['headers'])->postJson("/api/v1/styles/{$s['styleId']}/duplicate", array_merge($s['rec'], [
            'name' => 'Copy with bad op',
            'style_code' => 'COPY3',
            'operations' => [['operation_id' => 999999]],
        ]))->assertStatus(400);

        $this->assertSame(1, DB::table('styles')->count());
    }
}
