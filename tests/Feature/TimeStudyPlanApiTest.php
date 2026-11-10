<?php

namespace Tests\Feature;

use App\Http\Repositories\TimeStudyTemplateRepository as Templates;
use App\Support\FactoryContext;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Tests\Support\ProductFamilies;

class TimeStudyPlanApiTest extends TestCase
{
    use RefreshDatabase;

    private function factory(): int
    {
        return DB::table('factories')->insertGetId(['name' => 'Plant '.uniqid(), 'code' => 'PL'.strtoupper(substr(uniqid(), -6)), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function headersFor(int $factoryId, string $name = 'Planner'): array
    {
        // The factory scope is static and survives between requests in one test process; a real request starts clean.
        FactoryContext::reset();
        $userId = DB::table('users')->insertGetId([
            'name' => $name, 'email' => 'plan-'.uniqid().'@example.com', 'password' => bcrypt('Password1'),
            'is_active' => true, 'common_user_state' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('factory_user')->insert(['factory_id' => $factoryId, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser(User::find($userId)), 'X-Factory-Ids' => (string) $factoryId];
    }

    /** One operation, two operators in the factory, and a published template (as the signed-in user). */
    private function fixtures(int $factoryId, array $headers): array
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $baseCategoryId = DB::table('base_operation_categories')->insertGetId(['name' => 'Cat '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $baseOperationId = DB::table('base_operations')->insertGetId(['name' => 'Op '.$unique, 'code' => 'BO'.$unique, 'base_operation_category_id' => $baseCategoryId, 'created_at' => now(), 'updated_at' => now()]);
        $gradeId = DB::table('operation_grades')->insertGetId(['name' => 'Grade '.$unique, 'code' => 'OG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $groupId = DB::table('product_groups')->insertGetId(['name' => 'Group '.$unique, 'code' => 'PG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Category '.$unique, 'code' => 'PC'.$unique, 'product_family_id' => ProductFamilies::forGroup($groupId), 'created_at' => now(), 'updated_at' => now()]);
        $machineCategoryId = DB::table('machine_categories')->insertGetId(['name' => 'MC '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $machineId = DB::table('machine_types')->insertGetId(['name' => 'Machine '.$unique, 'code' => 'MT'.$unique, 'machine_category_id' => $machineCategoryId, 'created_at' => now(), 'updated_at' => now()]);
        $operationId = DB::table('operations')->insertGetId([
            'code' => 'OP'.$unique, 'description' => 'Operation '.$unique, 'base_operation_id' => $baseOperationId, 'grade_id' => $gradeId,
            'product_category_id' => $categoryId, 'machine_type_id' => $machineId, 'smv' => 0.5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $employees = [];
        foreach (['Jane', 'Joan'] as $i => $first) {
            $hierarchyId = DB::table('management_hierarchies')->insertGetId(['name' => 'Operator '.$unique.$i, 'seq_no' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $employees[] = DB::table('employees')->insertGetId([
                'employee_no' => 'EMP-'.$unique.$i, 'identification_no' => 'NIC-'.$unique.$i, 'first_name' => $first, 'last_name' => 'Doe',
                'management_hierarchy_id' => $hierarchyId, 'factory_id' => $factoryId, 'employee_status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $templateId = $this->postJson('/api/v1/timeStudyTemplates', ['name' => 'Plan template '.$unique, 'purpose' => 'perf'], $headers)->json('data.id');
        $this->postJson("/api/v1/timeStudyTemplates/$templateId/publish", [], $headers)->assertOk();

        return ['operation' => $operationId, 'employees' => $employees, 'template' => $templateId];
    }

    public function testThePlanEndpointsRequireAuthentication()
    {
        $this->getJson('/api/v1/timeStudyPlans')->assertStatus(401);
        $this->postJson('/api/v1/timeStudyPlans', [])->assertStatus(401);
        $this->postJson('/api/v1/timeStudyPlans/1/activate')->assertStatus(401);
    }

    public function testAPlanCannotBeCreatedForAFactoryTheUserHasNoAccessTo()
    {
        $mine = $this->factory();
        $other = $this->factory();
        $h = $this->headersFor($mine);
        $f = $this->fixtures($mine, $h);

        $this->postJson('/api/v1/timeStudyPlans', ['name' => 'Not mine', 'factory_id' => $other, 'template_id' => $f['template']], $h)
            ->assertStatus(400)->assertJsonPath('message', fn ($m) => str_contains($m, 'do not have access'));
    }

    public function testAPlanIsInvisibleToAnotherFactorysUsers()
    {
        $mine = $this->factory();
        $h = $this->headersFor($mine);
        $f = $this->fixtures($mine, $h);
        $planId = $this->postJson('/api/v1/timeStudyPlans', ['name' => 'Private', 'factory_id' => $mine, 'template_id' => $f['template']], $h)->assertOk()->json('data.id');

        $theirs = $this->headersFor($this->factory(), 'Outsider');

        $this->getJson("/api/v1/timeStudyPlans/$planId", $theirs)->assertStatus(400);
        $this->getJson('/api/v1/timeStudyPlans', $theirs)->assertOk()->assertJsonCount(0, 'data');
    }

    public function testAPlanCanBePlannedStartedClaimedRecordedAndFinishedOverHttp()
    {
        $factoryId = $this->factory();
        $h = $this->headersFor($factoryId);
        $f = $this->fixtures($factoryId, $h);

        $plan = $this->postJson('/api/v1/timeStudyPlans', [
            'name' => 'Collar day', 'factory_id' => $factoryId, 'template_id' => $f['template'], 'time_study_type' => 'interview_training',
            'target_per_pair' => 1, 'pairs' => [['operation_id' => $f['operation'], 'employee_id' => $f['employees'][0]]],
        ], $h)->assertOk()->assertJsonPath('data.status', 'draft')->json('data');
        $planId = $plan['id'];

        $this->postJson("/api/v1/timeStudyPlans/$planId/pairs", ['pairs' => [['operation_id' => $f['operation'], 'employee_id' => $f['employees'][1]]]], $h)
            ->assertOk()->assertJsonCount(2, 'data.items');
        $this->postJson("/api/v1/timeStudyPlans/$planId/activate", [], $h)->assertOk()->assertJsonPath('data.status', 'active');

        $itemId = $this->getJson("/api/v1/timeStudyPlans/$planId", $h)->json('data.items.0.id');
        $this->postJson("/api/v1/timeStudyPlans/$planId/pairs/$itemId/claim", [], $h)->assertOk()->assertJsonPath('data.items.0.claimed_by_me', true);

        $laps = [];
        for ($n = 1; $n <= 8; $n++) {
            $laps[] = ['lap_no' => $n, 'duration_ms' => 30000 + $n * 100, 'down_time_ms' => 0, 'net_ms' => 30000 + $n * 100, 'is_excluded' => false];
        }
        $this->postJson('/api/v1/timeStudies', [
            'factory_id' => $factoryId, 'study_date' => now()->toDateString(), 'time_study_type' => 'interview_training', 'operation_id' => $f['operation'],
            'employee_id' => $f['employees'][0], 'template_version_id' => $plan['template']['version_id'], 'time_study_plan_item_id' => $itemId,
            'total_down_time_ms' => 0, 'total_hold_ms' => 0, 'total_cycle_ms' => array_sum(array_column($laps, 'duration_ms')), 'laps' => $laps,
        ], $h)->assertOk();

        $this->getJson("/api/v1/timeStudyPlans/$planId", $h)->assertOk()
            ->assertJsonPath('data.progress.pairs_done', 1)
            ->assertJsonPath('data.items.0.done', true);
        $this->postJson("/api/v1/timeStudyPlans/$planId/complete", [], $h)->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function testThePlanReportRequiresAuthenticationAndIsNotMistakenForAPlanId()
    {
        $this->getJson('/api/v1/timeStudyPlans/report')->assertStatus(401);

        $factoryId = $this->factory();
        $h = $this->headersFor($factoryId);
        $f = $this->fixtures($factoryId, $h);
        $this->postJson('/api/v1/timeStudyPlans', ['name' => 'Reported', 'factory_id' => $factoryId, 'template_id' => $f['template']], $h)->assertOk();

        $this->getJson('/api/v1/timeStudyPlans/report', $h)->assertOk()
            ->assertJsonStructure(['data' => ['kpis' => ['plans', 'running', 'completion_pct'], 'plans', 'analysts']])
            ->assertJsonPath('data.kpis.plans', 1)
            ->assertJsonPath('data.plans.0.name', 'Reported');
    }

    public function testBreakingTheLayoutIsABadRequestWithTheReason()
    {
        $factoryId = $this->factory();
        $h = $this->headersFor($factoryId);
        $f = $this->fixtures($factoryId, $h);
        $planId = $this->postJson('/api/v1/timeStudyPlans', ['name' => 'No type', 'factory_id' => $factoryId, 'template_id' => $f['template']], $h)->json('data.id');

        $this->postJson("/api/v1/timeStudyPlans/$planId/activate", [], $h)
            ->assertStatus(400)->assertJsonPath('status', 'error')->assertJsonPath('message', fn ($m) => str_contains($m, 'study type'));
    }
}
