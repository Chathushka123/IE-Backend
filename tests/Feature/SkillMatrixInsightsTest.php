<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class SkillMatrixInsightsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fixtures are inserted via the query builder (not Eloquent::create) —
     * same rationale as TeamPlanTest: the models' boot() creating listener
     * requires an authenticated Auth::user(), which only exists once we're
     * inside an authenticated HTTP request.
     *
     * Also assigns the user to a factory via factory_user so
     * ResolveFactoryScope resolves a concrete, non-empty FactoryContext
     * (an unassigned user resolves to an empty allowed-factory list, which
     * would silently scope every query to zero rows).
     */
    private function authHeaders(int $factoryId): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Insights Tester',
            'email' => 'insights-tester-'.uniqid().'@example.com',
            'password' => bcrypt('Password1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('factory_user')->insert([
            'factory_id' => $factoryId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = JWTAuth::fromUser(User::find($userId));

        return [
            'Authorization' => "Bearer $token",
            'X-Factory-Ids' => (string) $factoryId,
        ];
    }

    private function makeFactory(): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 6));

        return DB::table('factories')->insertGetId([
            'name' => 'Plant '.$unique,
            'code' => 'PL'.$unique,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEmployee(int $factoryId): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));

        $managementHierarchyId = DB::table('management_hierarchies')->insertGetId([
            'name' => 'Operator '.$unique,
            'seq_no' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('employees')->insertGetId([
            'employee_no' => 'EMP-'.$unique,
            'identification_no' => 'NIC-'.$unique,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'management_hierarchy_id' => $managementHierarchyId,
            'factory_id' => $factoryId,
            'employee_status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeOperation(string $calculationMethod = 'mean', int $modeBinSizePct = 1): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));

        $baseOperationCategoryId = DB::table('base_operation_categories')->insertGetId([
            'name' => 'Sewing Operations '.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $baseOperationId = DB::table('base_operations')->insertGetId([
            'name' => 'Stitch Sleeve '.$unique,
            'code' => 'BO'.$unique,
            'base_operation_category_id' => $baseOperationCategoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $gradeId = DB::table('operation_grades')->insertGetId([
            'name' => 'Grade '.$unique,
            'code' => 'OG'.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productGroupId = DB::table('product_groups')->insertGetId([
            'name' => 'Knits '.$unique,
            'code' => 'KN'.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productCategoryId = DB::table('product_categories')->insertGetId([
            'name' => 'T-Shirts '.$unique,
            'code' => 'TS'.$unique,
            'product_group_id' => $productGroupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $machineCategoryId = DB::table('machine_categories')->insertGetId([
            'name' => 'Sewing Machines '.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $machineTypeId = DB::table('machine_types')->insertGetId([
            'name' => 'Single Needle '.$unique,
            'code' => 'MT'.$unique,
            'machine_category_id' => $machineCategoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('operations')->insertGetId([
            'code' => 'OP'.$unique,
            'base_operation_id' => $baseOperationId,
            'grade_id' => $gradeId,
            'product_category_id' => $productCategoryId,
            'machine_type_id' => $machineTypeId,
            'calculation_method' => $calculationMethod,
            'mode_bin_size_pct' => $modeBinSizePct,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertTimeStudy(int $factoryId, int $operationId, int $employeeId, float $efficiencyPct): void
    {
        DB::table('time_studies')->insert([
            'factory_id' => $factoryId,
            'study_date' => '2026-01-01',
            'time_study_type' => 'production_floor',
            'operation_id' => $operationId,
            'employee_id' => $employeeId,
            'efficiency_pct' => $efficiencyPct,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function testLatestReturnsNullDataBeforeAnyRunExists()
    {
        $factoryId = $this->makeFactory();
        $headers = $this->authHeaders($factoryId);

        $response = $this->withHeaders($headers)->getJson('/api/v1/skillMatrixInsights');

        $response->assertStatus(200)->assertJson([
            'status' => 'success',
            'data' => null,
        ]);
    }

    public function testRejectsUnauthenticatedRequests()
    {
        $response = $this->getJson('/api/v1/skillMatrixInsights');

        $response->assertStatus(401);
    }

    public function testRecalculateThenLatestThenCellReturnTheSavedMatrix()
    {
        $factoryId = $this->makeFactory();
        $headers = $this->authHeaders($factoryId);

        $employeeId = $this->makeEmployee($factoryId);
        $operationId = $this->makeOperation('mean', 1);

        $this->insertTimeStudy($factoryId, $operationId, $employeeId, 60);
        $this->insertTimeStudy($factoryId, $operationId, $employeeId, 80);

        $recalculateResponse = $this->withHeaders($headers)->postJson('/api/v1/skillMatrixInsights/recalculate', []);

        $recalculateResponse->assertStatus(200)->assertJson(['status' => 'success']);
        $recalculateResponse->assertJsonPath('data.run.study_count_total', 2);
        $recalculateResponse->assertJsonPath('data.run.cell_count', 1);
        $this->assertNotNull($recalculateResponse->json('data.run.calculated_by.id'));
        $recalculateResponse->assertJsonCount(1, 'data.cells');
        $recalculateResponse->assertJsonPath('data.cells.0.mean_efficiency', 70);
        $recalculateResponse->assertJsonPath('data.cells.0.selected_efficiency', 70);
        $recalculateResponse->assertJsonPath('data.cells.0.calculation_method_used', 'mean');

        $latestResponse = $this->withHeaders($headers)->getJson('/api/v1/skillMatrixInsights');

        $latestResponse->assertStatus(200)->assertJson(['status' => 'success']);
        $latestResponse->assertJsonPath('data.run.study_count_total', 2);
        $latestResponse->assertJsonPath('data.cells.0.selected_efficiency', 70);
        $latestResponse->assertJsonCount(1, 'data.operators');
        $latestResponse->assertJsonCount(1, 'data.operations');

        $cellResponse = $this->withHeaders($headers)->getJson(
            "/api/v1/skillMatrixInsights/cell?employee_id={$employeeId}&operation_id={$operationId}"
        );

        $cellResponse->assertStatus(200)->assertJson(['status' => 'success']);
        $cellResponse->assertJsonPath('data.employee.id', $employeeId);
        $cellResponse->assertJsonPath('data.operation.id', $operationId);
        $cellResponse->assertJsonPath('data.cell.study_count', 2);
        $cellResponse->assertJsonPath('data.cell.mean_efficiency', 70);
        $cellResponse->assertJsonCount(2, 'data.studies');
    }

    public function testRecalculateReplacesThePreviousRunForTheSameFactoryScope()
    {
        $factoryId = $this->makeFactory();
        $headers = $this->authHeaders($factoryId);

        $employeeId = $this->makeEmployee($factoryId);
        $operationId = $this->makeOperation('mean', 1);

        $this->insertTimeStudy($factoryId, $operationId, $employeeId, 50);

        $firstRunId = $this->withHeaders($headers)
            ->postJson('/api/v1/skillMatrixInsights/recalculate', [])
            ->json('data.run.id');

        $this->insertTimeStudy($factoryId, $operationId, $employeeId, 90);

        $secondRunId = $this->withHeaders($headers)
            ->postJson('/api/v1/skillMatrixInsights/recalculate', [])
            ->json('data.run.id');

        $this->assertNotEquals($firstRunId, $secondRunId);
        $this->assertEquals(1, DB::table('skill_matrix_calculation_runs')->count());
        $this->assertDatabaseHas('skill_matrix_calculation_cells', [
            'calculation_run_id' => $secondRunId,
            'mean_efficiency' => 70.00,
        ]);
    }
}
