<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Support\ProductFamilies;
use Tests\TestCase;

class StyleOperationCopyTest extends TestCase
{
    use RefreshDatabase;

    private array $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = ['created_at' => now(), 'updated_at' => now()];
    }

    /** @return array{categoryId:int,customerId:int,seasonId:int,factoryId:int,headers:array} */
    private function makeWorld(): array
    {
        $u = strtoupper(substr(md5(uniqid()), 0, 6));
        $groupId = DB::table('product_groups')->insertGetId(['name' => 'G'.$u, 'code' => 'G'.$u] + $this->now);
        $categoryId = $this->makeCategory($u, $groupId);
        $customerId = DB::table('customers')->insertGetId(['description' => 'Cu'.$u, 'code' => 'CU'.$u] + $this->now);
        $seasonId = DB::table('seasons')->insertGetId(['name' => 'Summer', 'code' => 'SU'.$u, 'customer_id' => $customerId] + $this->now);
        $factoryId = DB::table('factories')->insertGetId(['name' => 'F'.$u, 'code' => 'F'.$u, 'is_active' => true] + $this->now);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Copy Tester', 'email' => 'copy-'.uniqid().'@example.com', 'password' => bcrypt('Password1'),
            'is_active' => true, 'common_user_state' => 0,
        ] + $this->now);
        DB::table('factory_user')->insert(['factory_id' => $factoryId, 'user_id' => $userId] + $this->now);

        return [
            'categoryId' => $categoryId, 'customerId' => $customerId, 'seasonId' => $seasonId, 'factoryId' => $factoryId,
            'groupId' => $groupId,
            'headers' => [
                'Authorization' => 'Bearer '.JWTAuth::fromUser(User::withoutGlobalScopes()->find($userId)),
                'X-Factory-Ids' => (string) $factoryId,
            ],
        ];
    }

    private function makeCategory(string $suffix, int $groupId): int
    {
        return DB::table('product_categories')->insertGetId(['name' => 'C'.$suffix, 'code' => 'C'.$suffix, 'product_family_id' => ProductFamilies::forGroup($groupId)] + $this->now);
    }

    private function makeStyle(array $world, string $name, ?int $categoryId = null): int
    {
        $styleId = DB::table('styles')->insertGetId([
            'name' => $name, 'style_code' => strtoupper(substr(md5($name), 0, 8)),
            'product_category_id' => $categoryId ?? $world['categoryId'],
            'customer_id' => $world['customerId'], 'season_id' => $world['seasonId'], 'is_active' => true,
        ] + $this->now);
        DB::table('factory_style')->insert(['factory_id' => $world['factoryId'], 'style_id' => $styleId] + $this->now);

        return $styleId;
    }

    private function makeOperation(int $categoryId, string $description, float $smv): int
    {
        $baseCategoryId = DB::table('base_operation_categories')->insertGetId(['name' => 'BC'.uniqid()] + $this->now);
        $gradeId = DB::table('operation_grades')->insertGetId(['name' => 'G'.uniqid(), 'code' => 'OG'.substr(uniqid(), -6)] + $this->now);
        $machineCategoryId = DB::table('machine_categories')->insertGetId(['name' => 'MC'.uniqid()] + $this->now);
        $machineTypeId = DB::table('machine_types')->insertGetId(['name' => 'MT'.uniqid(), 'code' => 'MT'.substr(uniqid(), -6), 'machine_category_id' => $machineCategoryId] + $this->now);
        $baseOperationId = DB::table('base_operations')->insertGetId(['name' => 'BO'.uniqid(), 'code' => 'BO'.substr(uniqid(), -6), 'base_operation_category_id' => $baseCategoryId] + $this->now);

        return DB::table('operations')->insertGetId([
            'code' => 'OP'.substr(uniqid(), -6), 'description' => $description, 'base_operation_id' => $baseOperationId, 'grade_id' => $gradeId,
            'product_category_id' => $categoryId, 'machine_type_id' => $machineTypeId, 'smv' => $smv, 'is_active' => true,
        ] + $this->now);
    }

    private function addStep(int $styleId, int $operationId, int $sequenceNo, ?float $smv = null, bool $active = true): void
    {
        DB::table('style_operations')->insert([
            'style_id' => $styleId, 'operation_id' => $operationId, 'sequence_no' => $sequenceNo, 'smv' => $smv, 'is_active' => $active,
        ] + $this->now);
    }

    private function copy(array $world, int $targetId, int $sourceId, array $operations)
    {
        return $this->withHeaders($world['headers'])->postJson("/api/v1/styleOperations/copyFrom/{$targetId}", [
            'source_style_id' => $sourceId,
            'operations' => $operations,
        ]);
    }

    public function testAppendsTheChosenOperationsAfterTheTargetsLastStepInTheSourcesOrder()
    {
        $w = $this->makeWorld();
        $a = $this->makeStyle($w, 'Style A');
        $b = $this->makeStyle($w, 'Style B');
        $opSew = $this->makeOperation($w['categoryId'], 'Sew', 0.5);
        $opHem = $this->makeOperation($w['categoryId'], 'Hem', 0.25);
        $opCut = $this->makeOperation($w['categoryId'], 'Cut', 0.1);
        $this->addStep($a, $opCut, 1);
        $this->addStep($a, $opSew, 2, 0.9, false);   // A overrides SMV and has it inactive
        $this->addStep($a, $opHem, 3);
        $this->addStep($b, $opCut, 1);                // B already has Cut

        // Sent in the opposite order to A's bulletin on purpose.
        $response = $this->copy($w, $b, $a, [
            ['operation_id' => $opHem, 'smv' => 0.3],
            ['operation_id' => $opSew],
        ]);

        $response->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('meta.operations_copied', 2);
        // Source order (Sew then Hem), appended after B's step 1.
        $this->assertDatabaseHas('style_operations', ['style_id' => $b, 'operation_id' => $opSew, 'sequence_no' => 2, 'is_active' => 0]);
        $this->assertDatabaseHas('style_operations', ['style_id' => $b, 'operation_id' => $opHem, 'sequence_no' => 3, 'is_active' => 1]);
        // Override is kept when given; otherwise null (inherit the master) — A's own 0.9 is not carried over.
        $this->assertEquals(0.3, DB::table('style_operations')->where(['style_id' => $b, 'operation_id' => $opHem])->value('smv'));
        $this->assertNull(DB::table('style_operations')->where(['style_id' => $b, 'operation_id' => $opSew])->value('smv'));
        // The source is untouched.
        $this->assertSame(3, DB::table('style_operations')->where('style_id', $a)->count());
    }

    public function testRefusesStylesOfDifferentProductCategories()
    {
        $w = $this->makeWorld();
        $otherCategory = $this->makeCategory('X'.strtoupper(substr(md5(uniqid()), 0, 5)), $w['groupId']);
        $a = $this->makeStyle($w, 'Style A', $otherCategory);
        $b = $this->makeStyle($w, 'Style B');
        $op = $this->makeOperation($otherCategory, 'Sew', 0.5);
        $this->addStep($a, $op, 1);

        $this->copy($w, $b, $a, [['operation_id' => $op]])
            ->assertStatus(400)
            ->assertSee('same product silhouette', false);

        $this->assertSame(0, DB::table('style_operations')->where('style_id', $b)->count());
    }

    public function testRefusesAnOperationTheTargetAlreadyHasAndCopiesNothing()
    {
        $w = $this->makeWorld();
        $a = $this->makeStyle($w, 'Style A');
        $b = $this->makeStyle($w, 'Style B');
        $opNew = $this->makeOperation($w['categoryId'], 'Hem', 0.25);
        $opShared = $this->makeOperation($w['categoryId'], 'Sew', 0.5);
        $this->addStep($a, $opNew, 1);
        $this->addStep($a, $opShared, 2);
        $this->addStep($b, $opShared, 1);

        $this->copy($w, $b, $a, [['operation_id' => $opNew], ['operation_id' => $opShared]])
            ->assertStatus(400)
            ->assertSee('Already in', false);

        $this->assertSame(1, DB::table('style_operations')->where('style_id', $b)->count());
    }

    public function testRefusesAnOperationThatIsNotInTheSourcesBulletin()
    {
        $w = $this->makeWorld();
        $a = $this->makeStyle($w, 'Style A');
        $b = $this->makeStyle($w, 'Style B');
        $op = $this->makeOperation($w['categoryId'], 'Sew', 0.5);

        $this->copy($w, $b, $a, [['operation_id' => $op]])
            ->assertStatus(400)
            ->assertSee('no longer in', false);
    }

    public function testRefusesCopyingAStyleOntoItself()
    {
        $w = $this->makeWorld();
        $a = $this->makeStyle($w, 'Style A');
        $op = $this->makeOperation($w['categoryId'], 'Sew', 0.5);
        $this->addStep($a, $op, 1);

        $this->copy($w, $a, $a, [['operation_id' => $op]])->assertStatus(400);

        $this->assertSame(1, DB::table('style_operations')->where('style_id', $a)->count());
    }

    public function testRefusesTheSameOperationTwiceInOneRequest()
    {
        $w = $this->makeWorld();
        $a = $this->makeStyle($w, 'Style A');
        $b = $this->makeStyle($w, 'Style B');
        $op = $this->makeOperation($w['categoryId'], 'Sew', 0.5);
        $this->addStep($a, $op, 1);

        $this->copy($w, $b, $a, [['operation_id' => $op], ['operation_id' => $op]])->assertStatus(400);

        $this->assertSame(0, DB::table('style_operations')->where('style_id', $b)->count());
    }

    public function testValidatesTheRequestBody()
    {
        $w = $this->makeWorld();
        $a = $this->makeStyle($w, 'Style A');
        $b = $this->makeStyle($w, 'Style B');
        $op = $this->makeOperation($w['categoryId'], 'Sew', 0.5);
        $this->addStep($a, $op, 1);

        $this->copy($w, $b, $a, [])->assertStatus(422);
        $this->copy($w, $b, $a, [['operation_id' => $op, 'smv' => -1]])->assertStatus(422);
        $this->copy($w, $b, $a, [['operation_id' => $op, 'smv' => 'abc']])->assertStatus(422);
    }

    public function testRefusesASourceStyleOutsideTheUsersFactories()
    {
        $w = $this->makeWorld();
        $other = $this->makeWorld();
        $a = $this->makeStyle($other, 'Style A');          // in the other world's factory only
        $b = $this->makeStyle($w, 'Style B', $w['categoryId']);
        $op = $this->makeOperation($other['categoryId'], 'Sew', 0.5);
        $this->addStep($a, $op, 1);

        $this->copy($w, $b, $a, [['operation_id' => $op]])->assertStatus(400);

        $this->assertSame(0, DB::table('style_operations')->where('style_id', $b)->count());
    }

    public function testRequiresAuthentication()
    {
        $this->postJson('/api/v1/styleOperations/copyFrom/1', ['source_style_id' => 2, 'operations' => [['operation_id' => 1]]])
            ->assertStatus(401);
    }
}
