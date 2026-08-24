<?php

namespace Tests\Feature;

use App\User;
use Database\Seeders\GapAnalysisDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Proves the live Skill Depth endpoint (SkillDepthRepository::getReport())
 * correctly rolls up GapAnalysisDatasetSeeder's skill-matrix data into a
 * factory-wide (not team-scoped, unlike Gap Analysis) bus-factor report.
 * Expected values below were confirmed by actually running the endpoint
 * against the seeded dataset, not hand-derived in isolation.
 */
class SkillDepthTest extends TestCase
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
            'name' => 'Skill Depth Tester',
            'email' => 'skill-depth-tester-' . uniqid() . '@example.com',
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

    private function opFor(array $operations, int $operationId): ?array
    {
        foreach ($operations as $op) {
            if ($op['operation_id'] === $operationId) {
                return $op;
            }
        }

        return null;
    }

    public function testReportShapeAndKpis()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();
        $this->withHeaders($headers)->postJson('/api/v1/skillMatrixInsights/recalculate', []);

        $response = $this->withHeaders($headers)->getJson('/api/v1/skillDepth');
        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $data = $response->json('data');
        $this->assertCount(8, $data['operations']);
        $this->assertNotNull($data['run']);

        $this->assertEquals([
            'total_operations' => 8,
            'no_data_count' => 1,
            'zero_coverage_count' => 2,
            'single_point_of_failure_count' => 2,
            'thin_bench_count' => 3,
            'healthy_count' => 0,
            'at_risk_count' => 4,
            'coverage_pct' => 42.9,
        ], $data['kpis']);
    }

    public function testSinglePointOfFailureOperationsNameTheOneQualifiedOperator()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();
        $this->withHeaders($headers)->postJson('/api/v1/skillMatrixInsights/recalculate', []);
        $data = $this->withHeaders($headers)->getJson('/api/v1/skillDepth')->json('data');

        $op2 = $this->opFor($data['operations'], $this->opId('GAP-OP-02'));
        $this->assertEquals('single_point_of_failure', $op2['risk']);
        $this->assertCount(1, $op2['qualified_employees']);
        $this->assertEquals('GAP-EMP-101', $op2['qualified_employees'][0]['employee_no']);

        $op3 = $this->opFor($data['operations'], $this->opId('GAP-OP-03'));
        $this->assertEquals('single_point_of_failure', $op3['risk']);
        $this->assertEquals('GAP-EMP-102', $op3['qualified_employees'][0]['employee_no']);
    }

    public function testZeroCoverageIncludesTheStudiedButUnqualifiedAndTheResignedCases()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();
        $this->withHeaders($headers)->postJson('/api/v1/skillMatrixInsights/recalculate', []);
        $data = $this->withHeaders($headers)->getJson('/api/v1/skillDepth')->json('data');

        // GAP-OP-06: only-ever-studied employee is Active but scored below lower-mid.
        $op6 = $this->opFor($data['operations'], $this->opId('GAP-OP-06'));
        $this->assertEquals('zero_coverage', $op6['risk']);
        $this->assertTrue($op6['has_any_historical_data']);
        $this->assertCount(0, $op6['qualified_employees']);

        // GAP-OP-07: only-ever-qualified employee is Resigned, not active.
        $op7 = $this->opFor($data['operations'], $this->opId('GAP-OP-07'));
        $this->assertEquals('zero_coverage', $op7['risk']);
        $this->assertCount(0, $op7['qualified_employees']);
    }

    public function testNoDataOperationHasNoCellsAndNoQualifiedEmployees()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();
        $this->withHeaders($headers)->postJson('/api/v1/skillMatrixInsights/recalculate', []);
        $data = $this->withHeaders($headers)->getJson('/api/v1/skillDepth')->json('data');

        $op8 = $this->opFor($data['operations'], $this->opId('GAP-OP-08'));
        $this->assertEquals('no_data', $op8['risk']);
        $this->assertFalse($op8['has_any_historical_data']);
        $this->assertCount(0, $op8['qualified_employees']);
    }

    public function testOperationsAreSortedWorstFirst()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();
        $this->withHeaders($headers)->postJson('/api/v1/skillMatrixInsights/recalculate', []);
        $data = $this->withHeaders($headers)->getJson('/api/v1/skillDepth')->json('data');

        $riskOrder = ['no_data' => 0, 'zero_coverage' => 1, 'single_point_of_failure' => 2, 'thin_bench' => 3, 'healthy' => 4];
        $ranks = array_map(fn ($op) => $riskOrder[$op['risk']], $data['operations']);
        $sorted = $ranks;
        sort($sorted);
        $this->assertEquals($sorted, $ranks);
    }
}
