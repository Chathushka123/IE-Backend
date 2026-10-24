<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class TimeStudySmvApiTest extends TestCase
{
    use RefreshDatabase;

    private function reviewer(int $factoryId): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Reviewer', 'email' => 'smv-'.uniqid().'@example.com', 'password' => bcrypt('Password1'),
            'is_active' => true, 'common_user_state' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('factory_user')->insert(['factory_id' => $factoryId, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser(User::find($userId)), 'X-Factory-Ids' => (string) $factoryId];
    }

    /** A factory with one operation, two operators and a published Establish-SMV template that always needs review. */
    private function context(): array
    {
        $u = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $factory = DB::table('factories')->insertGetId(['name' => 'Plant '.$u, 'code' => 'PL'.substr($u, 0, 6), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $cat = DB::table('base_operation_categories')->insertGetId(['name' => 'Cat '.$u, 'created_at' => now(), 'updated_at' => now()]);
        $base = DB::table('base_operations')->insertGetId(['name' => 'Op '.$u, 'code' => 'BO'.$u, 'base_operation_category_id' => $cat, 'created_at' => now(), 'updated_at' => now()]);
        $grade = DB::table('operation_grades')->insertGetId(['name' => 'Grade '.$u, 'code' => 'OG'.$u, 'created_at' => now(), 'updated_at' => now()]);
        $group = DB::table('product_groups')->insertGetId(['name' => 'Group '.$u, 'code' => 'PG'.$u, 'created_at' => now(), 'updated_at' => now()]);
        $pcat = DB::table('product_categories')->insertGetId(['name' => 'PC '.$u, 'code' => 'PC'.$u, 'product_group_id' => $group, 'created_at' => now(), 'updated_at' => now()]);
        $mcat = DB::table('machine_categories')->insertGetId(['name' => 'MC '.$u, 'created_at' => now(), 'updated_at' => now()]);
        $machine = DB::table('machine_types')->insertGetId(['name' => 'Machine '.$u, 'code' => 'MT'.$u, 'machine_category_id' => $mcat, 'created_at' => now(), 'updated_at' => now()]);
        $operation = DB::table('operations')->insertGetId([
            'code' => 'OP'.$u, 'description' => 'Operation '.$u, 'base_operation_id' => $base, 'grade_id' => $grade, 'product_category_id' => $pcat,
            'machine_type_id' => $machine, 'smv' => 0.1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $employees = [];
        foreach ([0, 1] as $i) {
            $h = DB::table('management_hierarchies')->insertGetId(['name' => 'Operator '.$u.$i, 'seq_no' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $employees[] = DB::table('employees')->insertGetId([
                'employee_no' => 'EMP-'.$u.$i, 'identification_no' => 'NIC-'.$u.$i, 'first_name' => 'Op'.$i, 'last_name' => 'Tester',
                'management_hierarchy_id' => $h, 'factory_id' => $factory, 'employee_status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $template = DB::table('time_study_templates')->insertGetId(['name' => 'SMV '.$u, 'purpose' => 'smv', 'created_at' => now(), 'updated_at' => now()]);
        $version = DB::table('time_study_template_versions')->insertGetId([
            'time_study_template_id' => $template, 'version_no' => 1, 'status' => 'published', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'definition' => json_encode(['rules' => [
                'min_laps' => ['value' => 4, 'severity' => 'off'], 'outlier' => ['pct' => 30, 'severity' => 'off'], 'variation' => ['pct' => 20, 'severity' => 'off'],
                'down_time' => ['pct' => 15, 'severity' => 'off'], 'first_last' => 'ask', 'min_operators' => ['value' => 2, 'severity' => 'block'], 'rating_required' => true,
            ], 'fields' => [], 'counts_in_matrix' => false, 'approval' => 'always', 'allowance_pct' => 14]),
        ]);

        return compact('factory', 'operation', 'employees', 'version');
    }

    private function body(array $c): array
    {
        $run = fn (int $employeeId, int $rating) => [
            'employee_id' => $employeeId, 'rating_pct' => $rating, 'total_down_time_ms' => 0, 'total_hold_ms' => 0, 'total_cycle_ms' => 24000,
            'laps' => array_map(fn ($n) => ['lap_no' => $n, 'duration_ms' => 6000, 'down_time_ms' => 0, 'net_ms' => 6000, 'is_excluded' => false], [1, 2, 3, 4]),
        ];

        return [
            'factory_id' => $c['factory'], 'study_date' => '2026-10-20', 'time_study_type' => 'interview_training', 'operation_id' => $c['operation'],
            'template_version_id' => $c['version'], 'runs' => [$run($c['employees'][0], 100), $run($c['employees'][1], 120)],
        ];
    }

    public function testTheSmvRoutesRequireAuthentication()
    {
        $this->postJson('/api/v1/timeStudies/smvSession', [])->assertStatus(401);
        $this->getJson('/api/v1/timeStudies/1/smvProposal')->assertStatus(401);
        $this->postJson('/api/v1/timeStudies/1/applySmv', [])->assertStatus(401);
    }

    public function testASessionCanBeSavedReviewedAndAppliedOverHttp()
    {
        $c = $this->context();
        $h = $this->reviewer($c['factory']);

        $saved = $this->postJson('/api/v1/timeStudies/smvSession', $this->body($c), $h)
            ->assertOk()->assertJsonPath('data.status', 'needs_review')->assertJsonPath('data.proposal.proposed_smv', 0.1254);
        $leadId = $saved->json('data.study_ids.0');
        $otherId = $saved->json('data.study_ids.1');

        $this->getJson("/api/v1/timeStudies/$otherId/smvProposal", $h)
            ->assertOk()->assertJsonCount(2, 'data.runs')->assertJsonPath('data.default_target', 'operation')->assertJsonPath('data.applied', null);

        $this->postJson("/api/v1/timeStudies/$leadId/applySmv", [], $h)
            ->assertStatus(400)->assertJsonPath('message', fn ($m) => str_contains($m, 'Approve the study'));

        $this->postJson("/api/v1/timeStudies/$otherId/review", ['decision' => 'approved'], $h)->assertOk();
        $this->postJson("/api/v1/timeStudies/$leadId/applySmv", ['target' => 'operation'], $h)
            ->assertOk()->assertJsonPath('data.applied.new_smv', 0.1254)->assertJsonPath('data.applied.old_smv', 0.1);

        $this->assertEquals(0.1254, (float) DB::table('operations')->where('id', $c['operation'])->value('smv'));
        $this->postJson("/api/v1/timeStudies/$leadId/applySmv", [], $h)->assertStatus(400)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'already been applied'));
    }

    public function testASessionWithoutOperatorsIs400AndSavesNothing()
    {
        $c = $this->context();
        $h = $this->reviewer($c['factory']);
        $body = $this->body($c);
        $body['runs'] = [];

        $this->postJson('/api/v1/timeStudies/smvSession', $body, $h)->assertStatus(400)->assertJsonPath('status', 'error');
        $this->assertSame(0, DB::table('time_studies')->count());
    }

    public function testASessionBelowTheMinimumOperatorsIs400WithTheRuleNamed()
    {
        $c = $this->context();
        $h = $this->reviewer($c['factory']);
        $body = $this->body($c);
        $body['runs'] = [$body['runs'][0]];

        $this->postJson('/api/v1/timeStudies/smvSession', $body, $h)->assertStatus(400)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Operators timed'));
        $this->assertSame(0, DB::table('time_studies')->count());
    }
}
