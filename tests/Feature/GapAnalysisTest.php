<?php

namespace Tests\Feature;

use App\User;
use Database\Seeders\GapAnalysisDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Proves the live Gap Analysis endpoints (GapAnalysisRepository::getMatrix()/
 * getCellDetail()) correctly join GapAnalysisDatasetSeeder's demand
 * (team_plans + product_operations) against its supply (skill_matrix_
 * calculation_cells, via a Skill Matrix Insights recalculate) into the
 * documented gap scenarios.
 */
class GapAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function seedDataset(): void
    {
        $this->artisan('db:seed', ['--class' => GapAnalysisDatasetSeeder::class])
            ->assertExitCode(0);
    }

    private function authHeaders(): array
    {
        $factoryId = DB::table('factories')->where('code', 'GAP-DEMO')->value('id');

        $userId = DB::table('users')->insertGetId([
            'name' => 'Gap Analysis Tester',
            'email' => 'gap-analysis-tester-' . uniqid() . '@example.com',
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

    private function teamPlanId(string $teamCode): int
    {
        return DB::table('team_plans')
            ->join('teams', 'teams.id', '=', 'team_plans.team_id')
            ->where('teams.code', $teamCode)
            ->value('team_plans.id');
    }

    private function cellFor(array $cells, int $teamPlanId, int $operationId): ?array
    {
        foreach ($cells as $cell) {
            if ($cell['team_plan_id'] === $teamPlanId && $cell['operation_id'] === $operationId) {
                return $cell;
            }
        }

        return null;
    }

    public function testMatrixShapeAndKpis()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();
        $this->withHeaders($headers)->postJson('/api/v1/skillMatrixInsights/recalculate', []);

        $response = $this->withHeaders($headers)->getJson('/api/v1/gapAnalysis');
        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $data = $response->json('data');
        $this->assertCount(3, $data['rows']);
        $this->assertCount(8, $data['columns']); // union of GAP-P-A/B/C routings = all 8 seeded operations
        $this->assertCount(12, $data['cells']); // 4 routed ops x 3 plans
        $this->assertNotNull($data['run']);

        // Observed by actually running the endpoint against the seeded data
        // (see conversation/plan) — not hand-derived. All 3 plans have generous
        // 105-hour windows against modest quantities, so required_operators is
        // 1 everywhere; the mix below is driven entirely by qualification, not
        // headcount volume.
        $this->assertEquals([
            'total_cells' => 12,
            'ok_count' => 2,
            'quality_gap_count' => 3,
            'shortfall_count' => 0,
            'critical_count' => 6,
            'unknown_count' => 1,
            'readiness_pct' => 18.2,
            'teams_at_risk' => 3,
        ], $data['kpis']);
    }

    public function testUnambiguousSeededExtremesAndTheFactoryWideLabelAttachGap()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();
        $this->withHeaders($headers)->postJson('/api/v1/skillMatrixInsights/recalculate', []);

        $data = $this->withHeaders($headers)->getJson('/api/v1/gapAnalysis')->json('data');

        $alphaPlanId = $this->teamPlanId('GAP-T1');
        $betaPlanId = $this->teamPlanId('GAP-T2');
        $gammaPlanId = $this->teamPlanId('GAP-T3');

        // Beta / Final Inspection: only ever-qualified operator resigned -> zero active qualified.
        $criticalCell = $this->cellFor($data['cells'], $betaPlanId, $this->opId('GAP-OP-07'));
        $this->assertNotNull($criticalCell);
        $this->assertEquals('critical', $criticalCell['status']);
        $this->assertEquals(0, $criticalCell['qualified_active_count']);

        // Gamma / Elastic Waist Attach: zero time_studies at all, factory-wide.
        $unknownCell = $this->cellFor($data['cells'], $gammaPlanId, $this->opId('GAP-OP-08'));
        $this->assertNotNull($unknownCell);
        $this->assertEquals('unknown', $unknownCell['status']);
        $this->assertFalse($unknownCell['has_any_historical_data']);

        // Label Attach (in every product's routing) is critical for ALL THREE
        // teams: the only employee ever studied on it (GAP-EMP-101, on Alpha)
        // scored 55% mean efficiency, below the operation's 60% lower-mid bar
        // -> zero qualified active operators factory-wide, including on
        // Alpha itself, even though data exists.
        $op6 = $this->opId('GAP-OP-06');
        foreach ([$alphaPlanId, $betaPlanId, $gammaPlanId] as $planId) {
            $cell = $this->cellFor($data['cells'], $planId, $op6);
            $this->assertNotNull($cell);
            $this->assertEquals('critical', $cell['status']);
            $this->assertEquals(0, $cell['qualified_active_count']);
            $this->assertTrue($cell['has_any_historical_data']);
        }
    }

    public function testCellDetailForTheHeadcountCriticalOperationHasNoQualifiedOrTrainingCandidates()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();
        $this->withHeaders($headers)->postJson('/api/v1/skillMatrixInsights/recalculate', []);

        $betaPlanId = $this->teamPlanId('GAP-T2');
        $op7 = $this->opId('GAP-OP-07');

        $response = $this->withHeaders($headers)->getJson(
            "/api/v1/gapAnalysis/cell?team_plan_id={$betaPlanId}&operation_id={$op7}"
        );

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $data = $response->json('data');

        // GAP-EMP-201/202 (active Beta) have zero cells at all for Op-07 —
        // per spec, no cell at all means excluded from both qualified and
        // training-candidate lists (only a below-threshold cell counts as
        // a training candidate).
        $this->assertCount(0, $data['qualified']);
        $this->assertCount(0, $data['training_candidates']);
        // No other-team qualified operator exists in the seeded dataset.
        $this->assertCount(0, $data['reassignment_candidates']);
    }

    public function testCellDetailForTheQualityGapOperationListsBothGammaOperatorsAsQualifiedNotTraining()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();
        $this->withHeaders($headers)->postJson('/api/v1/skillMatrixInsights/recalculate', []);

        $gammaPlanId = $this->teamPlanId('GAP-T3');
        $op5 = $this->opId('GAP-OP-05');

        $response = $this->withHeaders($headers)->getJson(
            "/api/v1/gapAnalysis/cell?team_plan_id={$gammaPlanId}&operation_id={$op5}"
        );

        $response->assertStatus(200);
        $data = $response->json('data');

        // GAP-EMP-301 (63.00) and GAP-EMP-302 (70.00) both clear the lower-mid
        // bar (55) on Bartack, so both are "qualified", not "training candidates".
        $this->assertCount(2, $data['qualified']);
        $this->assertCount(0, $data['training_candidates']);
    }
}
