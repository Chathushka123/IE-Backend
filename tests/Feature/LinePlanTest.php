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

    private function makeProductionLine(array $overrides = []): int
    {
        $categoryId = DB::table('line_categories')->insertGetId([
            'description' => 'Sewing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'description' => 'Production',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('production_lines')->insertGetId(array_merge([
            'description' => 'Line A',
            'category_id' => $categoryId,
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
            'description' => 'Knits',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = DB::table('product_categories')->insertGetId([
            'description' => 'T-Shirts',
            'product_group_id' => $groupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'description' => 'Style 123',
            'product_category_id' => $categoryId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($smv > 0) {
            $operationCategoryId = DB::table('operation_categories')->insertGetId([
                'description' => 'Sewing Operations',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $operationId = DB::table('operations')->insertGetId([
                'description' => 'Stitch Sleeve',
                'operation_category_id' => $operationCategoryId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $gradeId = DB::table('operation_grades')->insertGetId([
                'description' => 'Grade A',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $gradingId = DB::table('operation_gradings')->insertGetId([
                'operation_id' => $operationId,
                'grade_id' => $gradeId,
                'product_category_id' => $categoryId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('product_operation_gradings')->insert([
                'product_id' => $productId,
                'operation_grading_id' => $gradingId,
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
            'description' => 'Operator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('employees')->insertGetId([
            'employee_no' => 'EMP-'.uniqid(),
            'nic_no' => 'NIC-'.uniqid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'category_id' => $categoryId,
            'production_line_id' => $productionLineId,
            'employee_status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function testCreatesALinePlanForAuthenticatedUser()
    {
        $lineId = $this->makeProductionLine();
        $productId = $this->makeProduct();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 500,
            'status' => 'planned',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('line_plans', [
            'production_line_id' => $lineId,
            'product_id' => $productId,
            'planned_quantity' => 500,
        ]);
    }

    public function testRejectsUnauthenticatedRequests()
    {
        $lineId = $this->makeProductionLine();
        $productId = $this->makeProduct();

        $response = $this->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 500,
        ]);

        $response->assertStatus(401);
    }

    public function testBlocksASecondInProgressAllocationOnTheSameLine()
    {
        $lineId = $this->makeProductionLine();
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 500,
            'status' => 'in_progress',
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineId,
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
        $lineOne = $this->makeProductionLine(['description' => 'Line A']);
        $lineTwo = $this->makeProductionLine(['description' => 'Line B']);
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineOne,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 300,
            'status' => 'in_progress',
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineTwo,
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
        $lineId = $this->makeProductionLine();
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 300,
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 100,
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('sequence number is already used', $response->getContent());
    }

    public function testResequencesQueueOrderForALine()
    {
        $lineId = $this->makeProductionLine();
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $first = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 100,
        ])->json('data.id');

        $second = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 2,
            'planned_quantity' => 200,
        ])->json('data.id');

        $response = $this->withHeaders($headers)->putJson('/api/v1/linePlans/resequence', [
            'production_line_id' => $lineId,
            'ids' => [$second, $first],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('line_plans', ['id' => $second, 'sequence_no' => 1]);
        $this->assertDatabaseHas('line_plans', ['id' => $first, 'sequence_no' => 2]);
    }

    public function testSuggestsAScheduleFromLineCapacityAndProductSmv()
    {
        $lineId = $this->makeProductionLine();
        $this->makeActiveEmployee($lineId);
        $productId = $this->makeProduct(0.5);
        $headers = $this->authHeaders();

        // capacity = (1 operator x 60 min x 55%) / 0.5 smv = 66 pcs/hour; hoursNeeded = ceil(1000/66) = 16
        $response = $this->withHeaders($headers)->getJson(
            "/api/v1/linePlans/suggestSchedule?production_line_id={$lineId}&product_id={$productId}&planned_quantity=1000"
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
        $lineId = $this->makeProductionLine();
        $productId = $this->makeProduct(0.5);
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->getJson(
            "/api/v1/linePlans/suggestSchedule?production_line_id={$lineId}&product_id={$productId}&planned_quantity=1000"
        );

        $response->assertStatus(400);
        $this->assertStringContainsString('no active operators assigned', $response->getContent());
    }

    public function testCreatesAChangeoverTaskWithoutProductOrQuantity()
    {
        $lineId = $this->makeProductionLine();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineId,
            'sequence_no' => 1,
            'is_changeover' => true,
            'notes' => 'Style changeover: knit to woven',
            'planned_start_date' => '2026-07-01 08:00:00',
            'planned_end_date' => '2026-07-01 09:30:00',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('line_plans', [
            'production_line_id' => $lineId,
            'is_changeover' => 1,
            'product_id' => null,
            'planned_quantity' => null,
            'notes' => 'Style changeover: knit to woven',
        ]);
    }

    public function testRejectsAMissingProductWhenNotAChangeover()
    {
        $lineId = $this->makeProductionLine();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineId,
            'sequence_no' => 1,
            'planned_quantity' => 100,
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('product_id', $response->getContent());
    }

    public function testPersistsHourPrecisionOnPlannedDates()
    {
        $lineId = $this->makeProductionLine();
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->postJson('/api/v1/linePlans', [
            'production_line_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 500,
            'planned_start_date' => '2026-07-01 08:30:00',
            'planned_end_date' => '2026-07-01 16:00:00',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('line_plans', [
            'production_line_id' => $lineId,
            'planned_start_date' => '2026-07-01 08:30:00',
            'planned_end_date' => '2026-07-01 16:00:00',
        ]);
    }
}
