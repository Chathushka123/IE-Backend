<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LinePlanTest extends TestCase
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

    private function makeTeam(array $overrides = []): int
    {
        $sectionId = DB::table('sections')->insertGetId([
            'name' => 'Sewing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Production',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('teams')->insertGetId(array_merge([
            'name' => 'Line A',
            'section_id' => $sectionId,
            'department_id' => $departmentId,
            'is_active' => true,
            'working_minutes_per_day' => 480,
            'target_efficiency_pct' => 55,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function makeProduct(float $smv = 0.5): int
    {
        $groupId = DB::table('product_groups')->insertGetId([
            'name' => 'Knits',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'T-Shirts',
            'product_group_id' => $groupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'name' => 'Style 123',
            'product_category_id' => $categoryId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($smv > 0) {
            $baseOperationCategoryId = DB::table('base_operation_categories')->insertGetId([
                'name' => 'Sewing Operations',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $baseOperationId = DB::table('base_operations')->insertGetId([
                'name' => 'Stitch Sleeve',
                'base_operation_category_id' => $baseOperationCategoryId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $gradeId = DB::table('operation_grades')->insertGetId([
                'name' => 'Grade A',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $operationId = DB::table('operations')->insertGetId([
                'base_operation_id' => $baseOperationId,
                'grade_id' => $gradeId,
                'product_category_id' => $categoryId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('product_operations')->insert([
                'product_id' => $productId,
                'operation_id' => $operationId,
                'sequence_no' => 1,
                'smv' => $smv,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $productId;
    }

    private function makeActiveEmployee(int $productionLineId): int
    {
        $categoryId = DB::table('employee_categories')->insertGetId([
            'name' => 'Operator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('employees')->insertGetId([
            'employee_no' => 'EMP-'.uniqid(),
            'nic_no' => 'NIC-'.uniqid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'category_id' => $categoryId,
            'team_id' => $productionLineId,
            'employee_status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function testCreatesALinePlanForAuthenticatedUser()
    {
        $lineId = $this->makeTeam();
        $productId = $this->makeProduct();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/linePlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 500,
            'status' => 'planned',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('line_plans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'planned_quantity' => 500,
        ]);
    }

    public function testRejectsUnauthenticatedRequests()
    {
        $lineId = $this->makeTeam();
        $productId = $this->makeProduct();

        $response = $this->postJson('/api/v1/linePlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 500,
        ]);

        $response->assertStatus(401);
    }

    public function testBlocksASecondInProgressAllocationOnTheSameLine()
    {
        $lineId = $this->makeTeam();
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 500,
            'status' => 'in_progress',
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 2,
            'planned_quantity' => 200,
            'status' => 'in_progress',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('already has another style in progress', $response->getContent());
    }

    public function testAllowsTheSameStyleToBeAllocatedToMultipleLines()
    {
        $lineOne = $this->makeTeam(['name' => 'Line A']);
        $lineTwo = $this->makeTeam(['name' => 'Line B']);
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'team_id' => $lineOne,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 300,
            'status' => 'in_progress',
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'team_id' => $lineTwo,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 400,
            'status' => 'in_progress',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertEquals(2, DB::table('line_plans')->where('product_id', $productId)->count());
    }

    public function testRejectsADuplicateSequenceNumberOnTheSameLine()
    {
        $lineId = $this->makeTeam();
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 300,
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 100,
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('sequence number is already used', $response->getContent());
    }

    public function testResequencesQueueOrderForALine()
    {
        $lineId = $this->makeTeam();
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $first = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 100,
        ])->json('data.id');

        $second = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 2,
            'planned_quantity' => 200,
        ])->json('data.id');

        $response = $this->withHeaders($headers)->putJson('/api/v1/linePlans/resequence', [
            'team_id' => $lineId,
            'ids' => [$second, $first],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('line_plans', ['id' => $second, 'sequence_no' => 1]);
        $this->assertDatabaseHas('line_plans', ['id' => $first, 'sequence_no' => 2]);
    }

    public function testSuggestsAScheduleFromLineCapacityAndProductSmv()
    {
        $lineId = $this->makeTeam();
        $this->makeActiveEmployee($lineId);
        $productId = $this->makeProduct(0.5);
        $headers = $this->authHeaders();

        // capacity = (1 operator x 60 min x 55%) / 0.5 smv = 66 pcs/hour; hoursNeeded = ceil(1000/66) = 16
        $response = $this->withHeaders($headers)->getJson(
            "/api/v1/linePlans/suggestSchedule?team_id={$lineId}&product_id={$productId}&planned_quantity=1000"
        );

        $response->assertStatus(200)->assertJson([
            'status' => 'success',
            'data' => [
                'operator_count' => 1,
                'hourly_capacity' => 66,
                'hours_needed' => 16,
            ],
        ]);
    }

    public function testSuggestionFailsCleanlyWhenLineHasNoActiveOperators()
    {
        $lineId = $this->makeTeam();
        $productId = $this->makeProduct(0.5);
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->getJson(
            "/api/v1/linePlans/suggestSchedule?team_id={$lineId}&product_id={$productId}&planned_quantity=1000"
        );

        $response->assertStatus(400);
        $this->assertStringContainsString('no active operators assigned', $response->getContent());
    }

    public function testCreatesAChangeoverTaskWithoutProductOrQuantity()
    {
        $lineId = $this->makeTeam();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'team_id' => $lineId,
            'sequence_no' => 1,
            'is_changeover' => true,
            'notes' => 'Style changeover: knit to woven',
            'planned_start_date' => '2026-07-01 08:00:00',
            'planned_end_date' => '2026-07-01 09:30:00',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('line_plans', [
            'team_id' => $lineId,
            'is_changeover' => 1,
            'product_id' => null,
            'planned_quantity' => null,
            'notes' => 'Style changeover: knit to woven',
        ]);
    }

    public function testRejectsAMissingProductWhenNotAChangeover()
    {
        $lineId = $this->makeTeam();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'team_id' => $lineId,
            'sequence_no' => 1,
            'planned_quantity' => 100,
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('product_id', $response->getContent());
    }

    public function testPersistsHourPrecisionOnPlannedDates()
    {
        $lineId = $this->makeTeam();
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 500,
            'planned_start_date' => '2026-07-01 08:30:00',
            'planned_end_date' => '2026-07-01 16:00:00',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('line_plans', [
            'team_id' => $lineId,
            'planned_start_date' => '2026-07-01 08:30:00',
            'planned_end_date' => '2026-07-01 16:00:00',
        ]);
    }
}
