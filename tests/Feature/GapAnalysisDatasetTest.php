<?php

namespace Tests\Feature;

use App\User;
use Database\Seeders\GapAnalysisDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Proves the GapAnalysisDatasetSeeder's hand-computed scenario table (see its
 * own docblock) actually flows through the real SkillMatrixCalculationRepository
 * and TeamPlanRepository::suggestSchedule() and produces exactly the numbers
 * documented there — not just that the seeder inserts rows without error.
 */
class GapAnalysisDatasetTest extends TestCase
{
    use RefreshDatabase;

    private function seedDataset(): void
    {
        $this->artisan('db:seed', ['--class' => GapAnalysisDatasetSeeder::class])
            ->assertExitCode(0);
    }

    /** Same rationale as SkillMatrixInsightsTest::authHeaders() — a user must
     *  be linked via factory_user or FactoryContext resolves to an empty scope. */
    private function authHeaders(): array
    {
        $factoryId = DB::table('factories')->where('code', 'GAP-DEMO')->value('id');

        $userId = DB::table('users')->insertGetId([
            'name' => 'Gap Dataset Tester',
            'email' => 'gap-dataset-tester-' . uniqid() . '@example.com',
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

    private function opId(string $code): int
    {
        return DB::table('operations')->where('code', $code)->value('id');
    }

    private function empId(string $employeeNo): int
    {
        return DB::table('employees')->where('employee_no', $employeeNo)->value('id');
    }

    private function teamId(string $code): int
    {
        return DB::table('teams')->where('code', $code)->value('id');
    }

    private function productId(string $styleCode): int
    {
        return DB::table('products')->where('style_code', $styleCode)->value('id');
    }

    public function testRecalculateProducesAllElevenHandComputedCells()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->postJson('/api/v1/skillMatrixInsights/recalculate', []);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $response->assertJsonPath('data.run.study_count_total', 35);
        $response->assertJsonPath('data.run.cell_count', 11);

        $expectedCells = [
            ['GAP-EMP-101', 'GAP-OP-01', 3, 90.00, 90.00, 'mean', 90.00],
            ['GAP-EMP-102', 'GAP-OP-01', 3, 80.00, 80.00, 'mean', 80.00],
            ['GAP-EMP-104', 'GAP-OP-01', 3, 45.00, 45.00, 'mean', 45.00],
            ['GAP-EMP-101', 'GAP-OP-02', 3, 94.00, 94.00, 'mean', 94.00],
            ['GAP-EMP-101', 'GAP-OP-06', 3, 55.00, 55.00, 'mean', 55.00],
            ['GAP-EMP-102', 'GAP-OP-03', 3, 70.00, 60.00, 'median', 60.00],
            ['GAP-EMP-201', 'GAP-OP-04', 3, 75.00, 70.00, 'median', 70.00],
            ['GAP-EMP-202', 'GAP-OP-04', 3, 66.00, 66.00, 'median', 66.00],
            ['GAP-EMP-203', 'GAP-OP-07', 3, 75.00, 75.00, 'mean', 75.00],
        ];

        foreach ($expectedCells as [$employeeNo, $opCode, $studyCount, $mean, $median, $method, $selected]) {
            $this->assertDatabaseHas('skill_matrix_calculation_cells', [
                'employee_id' => $this->empId($employeeNo),
                'operation_id' => $this->opId($opCode),
                'study_count' => $studyCount,
                'mean_efficiency' => $mean,
                'median_efficiency' => $median,
                'calculation_method_used' => $method,
                'selected_efficiency' => $selected,
            ]);
        }

        // Cell 9 — real mode win (largest bucket has >1 value, no fallback).
        $cell9 = DB::table('skill_matrix_calculation_cells')
            ->where('employee_id', $this->empId('GAP-EMP-301'))
            ->where('operation_id', $this->opId('GAP-OP-05'))
            ->first();

        $this->assertEquals(5, $cell9->study_count);
        $this->assertEquals(72.80, (float) $cell9->mean_efficiency);
        $this->assertEquals(64.00, (float) $cell9->median_efficiency);
        $this->assertEquals(63.00, (float) $cell9->mode_efficiency);
        $this->assertEquals(0, (int) $cell9->mode_used_fallback_to_mean);
        $this->assertEqualsWithDelta(14.4465, (float) $cell9->stddev_efficiency, 0.001);
        $this->assertEquals('mode', $cell9->calculation_method_used);
        $this->assertEquals(63.00, (float) $cell9->selected_efficiency);

        // Cell 10 — mode fallback to mean (every bucket singleton).
        $cell10 = DB::table('skill_matrix_calculation_cells')
            ->where('employee_id', $this->empId('GAP-EMP-302'))
            ->where('operation_id', $this->opId('GAP-OP-05'))
            ->first();

        $this->assertEquals(70.00, (float) $cell10->mean_efficiency);
        $this->assertNull($cell10->mode_efficiency);
        $this->assertEquals(1, (int) $cell10->mode_used_fallback_to_mean);
        $this->assertEquals(70.00, (float) $cell10->selected_efficiency);
    }

    public function testHeadcountGapMeansNoActiveBetaEmployeeHasACellForFinalInspection()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();
        $this->withHeaders($headers)->postJson('/api/v1/skillMatrixInsights/recalculate', []);

        $op7 = $this->opId('GAP-OP-07');

        $this->assertDatabaseMissing('skill_matrix_calculation_cells', [
            'employee_id' => $this->empId('GAP-EMP-201'),
            'operation_id' => $op7,
        ]);
        $this->assertDatabaseMissing('skill_matrix_calculation_cells', [
            'employee_id' => $this->empId('GAP-EMP-202'),
            'operation_id' => $op7,
        ]);
        $this->assertDatabaseHas('skill_matrix_calculation_cells', [
            'employee_id' => $this->empId('GAP-EMP-203'),
            'operation_id' => $op7,
        ]);
        $this->assertEquals('Resigned', DB::table('employees')->where('employee_no', 'GAP-EMP-203')->value('employee_status'));
        $this->assertEquals('Active', DB::table('employees')->where('employee_no', 'GAP-EMP-201')->value('employee_status'));
    }

    public function testOperationEightHasNoSkillDataAtAllDespiteBeingInAProductRouting()
    {
        $this->seedDataset();

        $op8 = $this->opId('GAP-OP-08');

        $this->assertEquals(0, DB::table('time_studies')->where('operation_id', $op8)->count());
        $this->assertDatabaseHas('product_operations', [
            'product_id' => $this->productId('GAP-P-C'),
            'operation_id' => $op8,
        ]);
    }

    public function testSuggestScheduleForFullyStaffedTeamAlpha()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/teamPlans/suggestSchedule?team_id=' . $this->teamId('GAP-T1')
            . '&product_id=' . $this->productId('GAP-P-A')
            . '&planned_quantity=1800'
        );

        $response->assertStatus(200)->assertJson([
            'status' => 'success',
            'data' => [
                'operator_count' => 6,
                'hourly_capacity' => 180,
                'hours_needed' => 10,
            ],
        ]);
    }

    public function testSuggestScheduleForHeadcountConstrainedTeamBeta()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/teamPlans/suggestSchedule?team_id=' . $this->teamId('GAP-T2')
            . '&product_id=' . $this->productId('GAP-P-B')
            . '&planned_quantity=288'
        );

        $response->assertStatus(200)->assertJson([
            'status' => 'success',
            'data' => [
                'operator_count' => 2,
                'hourly_capacity' => 36,
                'hours_needed' => 8,
            ],
        ]);
    }

    public function testSuggestScheduleForQualityGapTeamGamma()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/teamPlans/suggestSchedule?team_id=' . $this->teamId('GAP-T3')
            . '&product_id=' . $this->productId('GAP-P-C')
            . '&planned_quantity=360'
        );

        $response->assertStatus(200)->assertJson([
            'status' => 'success',
            'data' => [
                'operator_count' => 4,
                'hourly_capacity' => 60,
                'hours_needed' => 6,
            ],
        ]);
    }

    public function testSeederIsIdempotentWhenRunTwice()
    {
        $this->seedDataset();
        $this->seedDataset();

        $factoryId = DB::table('factories')->where('code', 'GAP-DEMO')->value('id');

        $this->assertEquals(1, DB::table('factories')->where('code', 'GAP-DEMO')->count());
        $this->assertEquals(15, DB::table('employees')->where('factory_id', $factoryId)->count());
        $this->assertEquals(35, DB::table('time_studies')->where('factory_id', $factoryId)->count());
        $this->assertEquals(3, DB::table('teams')->where('factory_id', $factoryId)->count());
        $this->assertEquals(8, DB::table('operations')->where('code', 'LIKE', 'GAP-OP-%')->count());
    }
}
