<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class TeamPlanTest extends TestCase
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
        $unique = strtoupper(substr(md5(uniqid()), 0, 6));

        $sectionId = DB::table('sections')->insertGetId([
            'name' => 'Sewing '.$unique,
            'code' => 'SEW'.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Production '.$unique,
            'code' => 'PRD'.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $factoryId = DB::table('factories')->insertGetId([
            'name' => 'Plant '.$unique,
            'code' => 'PL'.$unique,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('teams')->insertGetId(array_merge([
            'name' => 'Line A',
            'code' => 'LA'.$unique,
            'section_id' => $sectionId,
            'department_id' => $departmentId,
            'factory_id' => $factoryId,
            'is_active' => true,
            'working_minutes_per_day' => 480,
            'target_efficiency_pct' => 55,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function makeProduct(float $smv = 0.5): int
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
        $productId = DB::table('products')->insertGetId([
            'name' => 'Style 123'.$unique,
            'style_code' => 'ST'.$unique,
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($smv > 0) {
            $baseOperationCategoryId = DB::table('base_operation_categories')->insertGetId([
                'name' => 'Sewing Operations'.$unique,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $baseOperationId = DB::table('base_operations')->insertGetId([
                'name' => 'Stitch Sleeve'.$unique,
                'code' => 'BO'.$unique,
                'base_operation_category_id' => $baseOperationCategoryId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $gradeId = DB::table('operation_grades')->insertGetId([
                'name' => 'Grade A'.$unique,
                'code' => 'OG'.$unique,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $machineCategoryId = DB::table('machine_categories')->insertGetId([
                'name' => 'Sewing Machines'.$unique,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $machineTypeId = DB::table('machine_types')->insertGetId([
                'name' => 'Single Needle'.$unique,
                'code' => 'MT'.$unique,
                'machine_category_id' => $machineCategoryId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $operationId = DB::table('operations')->insertGetId([
                'code' => 'OP'.$unique,
                'base_operation_id' => $baseOperationId,
                'grade_id' => $gradeId,
                'product_category_id' => $categoryId,
                'machine_type_id' => $machineTypeId,
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
            'name' => 'Operator'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $factoryId = DB::table('teams')->where('id', $productionLineId)->value('factory_id');

        return DB::table('employees')->insertGetId([
            'employee_no' => 'EMP-'.uniqid(),
            'identification_no' => 'NIC-'.uniqid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'category_id' => $categoryId,
            'team_id' => $productionLineId,
            'factory_id' => $factoryId,
            'employee_status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function testCreatesATeamPlanForAuthenticatedUser()
    {
        $lineId = $this->makeTeam();
        $productId = $this->makeProduct();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/teamPlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 500,
            'status' => 'planned',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('team_plans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'planned_quantity' => 500,
        ]);
    }

    public function testRejectsUnauthenticatedRequests()
    {
        $lineId = $this->makeTeam();
        $productId = $this->makeProduct();

        $response = $this->postJson('/api/v1/teamPlans', [
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

        $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 500,
            'status' => 'in_progress',
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
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

        $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
            'team_id' => $lineOne,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 300,
            'status' => 'in_progress',
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
            'team_id' => $lineTwo,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 400,
            'status' => 'in_progress',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertEquals(2, DB::table('team_plans')->where('product_id', $productId)->count());
    }

    public function testRejectsADuplicateSequenceNumberOnTheSameLine()
    {
        $lineId = $this->makeTeam();
        $productId = $this->makeProduct();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 300,
        ])->assertStatus(200);

        $response = $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
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

        $first = $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 100,
        ])->json('data.id');

        $second = $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 2,
            'planned_quantity' => 200,
        ])->json('data.id');

        $response = $this->withHeaders($headers)->putJson('/api/v1/teamPlans/resequence', [
            'team_id' => $lineId,
            'ids' => [$second, $first],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('team_plans', ['id' => $second, 'sequence_no' => 1]);
        $this->assertDatabaseHas('team_plans', ['id' => $first, 'sequence_no' => 2]);
    }

    public function testSuggestsAScheduleFromLineCapacityAndProductSmv()
    {
        $lineId = $this->makeTeam();
        $this->makeActiveEmployee($lineId);
        $productId = $this->makeProduct(0.5);
        $headers = $this->authHeaders();

        // capacity = (1 operator x 60 min x 55%) / 0.5 smv = 66 pcs/hour; hoursNeeded = ceil(1000/66) = 16
        $response = $this->withHeaders($headers)->getJson(
            "/api/v1/teamPlans/suggestSchedule?team_id={$lineId}&product_id={$productId}&planned_quantity=1000"
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
            "/api/v1/teamPlans/suggestSchedule?team_id={$lineId}&product_id={$productId}&planned_quantity=1000"
        );

        $response->assertStatus(400);
        $this->assertStringContainsString('no active operators assigned', $response->getContent());
    }

    public function testCreatesAChangeoverTaskWithoutProductOrQuantity()
    {
        $lineId = $this->makeTeam();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
            'team_id' => $lineId,
            'sequence_no' => 1,
            'is_changeover' => true,
            'notes' => 'Style changeover: knit to woven',
            'planned_start_date' => '2026-07-01 08:00:00',
            'planned_end_date' => '2026-07-01 09:30:00',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('team_plans', [
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

        $response = $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
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

        $response = $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
            'team_id' => $lineId,
            'product_id' => $productId,
            'sequence_no' => 1,
            'planned_quantity' => 500,
            'planned_start_date' => '2026-07-01 08:30:00',
            'planned_end_date' => '2026-07-01 16:00:00',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('team_plans', [
            'team_id' => $lineId,
            'planned_start_date' => '2026-07-01 08:30:00',
            'planned_end_date' => '2026-07-01 16:00:00',
        ]);
    }
}
