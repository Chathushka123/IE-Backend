<?php

namespace Tests\Feature;

use App\User;
use Database\Seeders\TeamPlanScheduleDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Proves the TeamPlanScheduleDatasetSeeder's documented scenario (see its own
 * docblock) actually holds once seeded — the back-to-back plans are all
 * present, the same product legitimately repeats on the same team on
 * disjoint ranges, and the "would be rejected" overlap claims are true
 * against the real assertNoOverlap guard, not just asserted in a comment.
 */
class TeamPlanScheduleDatasetTest extends TestCase
{
    use RefreshDatabase;

    private function seedDataset(): void
    {
        $this->artisan('db:seed', ['--class' => TeamPlanScheduleDatasetSeeder::class])
            ->assertExitCode(0);
    }

    private function authHeaders(): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Schedule Dataset Tester',
            'email' => 'schedule-dataset-tester-' . uniqid() . '@example.com',
            'password' => bcrypt('Password1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = JWTAuth::fromUser(User::find($userId));

        return ['Authorization' => "Bearer $token"];
    }

    private function teamId(string $code): int
    {
        return DB::table('teams')->where('code', $code)->value('id');
    }

    private function productId(string $styleCode): int
    {
        return DB::table('products')->where('style_code', $styleCode)->value('id');
    }

    public function testSeedsTheDocumentedTeamPlanSchedule()
    {
        $this->seedDataset();

        $t1 = $this->teamId('SCHED-T1');
        $t2 = $this->teamId('SCHED-T2');
        $p1 = $this->productId('SCHED-P1');
        $p3 = $this->productId('SCHED-P3');

        $this->assertEquals(4, DB::table('team_plans')->where('team_id', $t1)->count());
        $this->assertEquals(3, DB::table('team_plans')->where('team_id', $t2)->count());

        // Style One and Style Three each run twice on SCHED-T1, on disjoint ranges.
        $this->assertEquals(2, DB::table('team_plans')->where('team_id', $t1)->where('product_id', $p1)->count());
        $this->assertEquals(2, DB::table('team_plans')->where('team_id', $t1)->where('product_id', $p3)->count());

        $this->assertDatabaseHas('team_plans', [
            'team_id' => $t1, 'product_id' => $p1,
            'planned_start_date' => '2026-09-01', 'planned_end_date' => '2026-09-05',
        ]);
        $this->assertDatabaseHas('team_plans', [
            'team_id' => $t1, 'product_id' => $p3,
            'planned_start_date' => '2026-09-06', 'planned_end_date' => '2026-09-09',
        ]);
        $this->assertDatabaseHas('team_plans', [
            'team_id' => $t1, 'product_id' => $p1,
            'planned_start_date' => '2026-09-10', 'planned_end_date' => '2026-09-15',
        ]);
        $this->assertDatabaseHas('team_plans', [
            'team_id' => $t1, 'product_id' => $p3,
            'planned_start_date' => '2026-09-16', 'planned_end_date' => '2026-09-20',
        ]);

        // The trailing changeover on SCHED-T2 has no product/quantity.
        $this->assertDatabaseHas('team_plans', [
            'team_id' => $t2, 'is_changeover' => 1, 'product_id' => null, 'planned_quantity' => null,
            'planned_start_date' => '2026-09-16', 'planned_end_date' => '2026-09-16',
        ]);
    }

    public function testSeedsTheDocumentedMilestoneSpread()
    {
        $this->seedDataset();

        // Style One: cutting done, one day later than planned. planned_quantity (2200)
        // is the sum of its two team_plan segments (1000 + 1200).
        $this->assertDatabaseHas('product_milestones', [
            'product_id' => $this->productId('SCHED-P1'),
            'planned_quantity' => 2200,
            'planned_cut_date' => '2026-08-20',
            'actual_cut_date' => '2026-08-21',
        ]);

        // Style Two: nothing actualized yet.
        $milestoneP2 = DB::table('product_milestones')->where('product_id', $this->productId('SCHED-P2'))->first();
        $this->assertNull($milestoneP2->actual_cut_date);
        $this->assertNull($milestoneP2->actual_production_start_date);
        $this->assertNull($milestoneP2->actual_eta);

        // Style Three: cutting finished four days late.
        $this->assertDatabaseHas('product_milestones', [
            'product_id' => $this->productId('SCHED-P3'),
            'planned_cut_date' => '2026-08-15',
            'actual_cut_date' => '2026-08-19',
        ]);
    }

    public function testDocumentedOverlapClaimIsRejectedByTheRealGuard()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();

        // Per the seeder's docblock: this shares 2026-09-09 with Style One's second
        // run (2026-09-10..2026-09-15)? No — it shares 09-09 with Style Three's
        // first run (2026-09-06..2026-09-09), which is the documented example.
        $response = $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
            'team_id' => $this->teamId('SCHED-T1'),
            'product_id' => $this->productId('SCHED-P1'),
            'sequence_no' => 99,
            'planned_quantity' => 100,
            'planned_start_date' => '2026-09-09',
            'planned_end_date' => '2026-09-11',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('already scheduled', $response->getContent());
    }

    public function testBackToBackDayAfterTheLastPlanIsStillAccepted()
    {
        $this->seedDataset();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->postJson('/api/v1/teamPlans', [
            'team_id' => $this->teamId('SCHED-T1'),
            'product_id' => $this->productId('SCHED-P1'),
            'sequence_no' => 5,
            'planned_quantity' => 500,
            'planned_start_date' => '2026-09-21',
            'planned_end_date' => '2026-09-25',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
    }

    public function testSeederIsIdempotentWhenRunTwice()
    {
        $this->seedDataset();
        $this->seedDataset();

        $factoryId = DB::table('factories')->where('code', 'SCHED-DEMO')->value('id');

        $this->assertEquals(1, DB::table('factories')->where('code', 'SCHED-DEMO')->count());
        $this->assertEquals(2, DB::table('teams')->where('factory_id', $factoryId)->count());
        $this->assertEquals(3, DB::table('products')->where('style_code', 'LIKE', 'SCHED-P%')->count());
        $this->assertEquals(7, DB::table('team_plans')->where('team_id', $this->teamId('SCHED-T1'))
            ->orWhere('team_id', $this->teamId('SCHED-T2'))->count());
        $this->assertEquals(3, DB::table('product_milestones')->count());
    }
}
