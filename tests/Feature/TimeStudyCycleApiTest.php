<?php

namespace Tests\Feature;

use App\Support\FactoryContext;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class TimeStudyCycleApiTest extends TestCase
{
    use RefreshDatabase;

    private function setupFor(): array
    {
        FactoryContext::reset();
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $factoryId = DB::table('factories')->insertGetId(['name' => 'Plant '.$unique, 'code' => 'PL'.substr($unique, 0, 6), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $userId = DB::table('users')->insertGetId(['name' => 'Cycle Tester', 'email' => 'cyc-'.$unique.'@example.com', 'password' => bcrypt('Password1'), 'is_active' => true, 'common_user_state' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('factory_user')->insert(['factory_id' => $factoryId, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        $headers = ['Authorization' => 'Bearer '.JWTAuth::fromUser(User::find($userId)), 'X-Factory-Ids' => (string) $factoryId];

        $baseCategoryId = DB::table('base_operation_categories')->insertGetId(['name' => 'Cat '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $gradeId = DB::table('operation_grades')->insertGetId(['name' => 'Grade '.$unique, 'code' => 'OG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $groupId = DB::table('product_groups')->insertGetId(['name' => 'Group '.$unique, 'code' => 'PG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Category '.$unique, 'code' => 'PC'.$unique, 'product_group_id' => $groupId, 'created_at' => now(), 'updated_at' => now()]);
        $machineCategoryId = DB::table('machine_categories')->insertGetId(['name' => 'MC '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $machineId = DB::table('machine_types')->insertGetId(['name' => 'Machine '.$unique, 'code' => 'MT'.$unique, 'machine_category_id' => $machineCategoryId, 'created_at' => now(), 'updated_at' => now()]);
        $operations = [];
        foreach ([1, 2] as $i) {
            $baseId = DB::table('base_operations')->insertGetId(['name' => "Op {$i} {$unique}", 'code' => "BO{$i}{$unique}", 'base_operation_category_id' => $baseCategoryId, 'created_at' => now(), 'updated_at' => now()]);
            $operations[] = DB::table('operations')->insertGetId([
                'code' => "OP{$i}{$unique}", 'description' => "Operation {$i}", 'base_operation_id' => $baseId, 'grade_id' => $gradeId, 'sequence_no' => $i,
                'product_category_id' => $categoryId, 'machine_type_id' => $machineId, 'smv' => 0.5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $hierarchyId = DB::table('management_hierarchies')->insertGetId(['name' => 'Operator '.$unique, 'seq_no' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $employee = DB::table('employees')->insertGetId([
            'employee_no' => 'EMP-'.$unique, 'identification_no' => 'NIC-'.$unique, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'management_hierarchy_id' => $hierarchyId, 'factory_id' => $factoryId, 'employee_status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('factoryId', 'headers', 'operations', 'employee');
    }

    private function laps(int $count): array
    {
        $laps = [];
        for ($n = 1; $n <= $count; $n++) {
            $laps[] = ['lap_no' => $n, 'duration_ms' => 30000 + $n * 100, 'down_time_ms' => 0, 'net_ms' => 30000 + $n * 100, 'is_excluded' => false];
        }

        return $laps;
    }

    private function body(array $s, int $laps = 8): array
    {
        return [
            'factory_id' => $s['factoryId'], 'study_date' => now()->toDateString(), 'time_study_type' => 'interview_training', 'employee_id' => $s['employee'],
            'operations' => array_map(fn ($id) => ['operation_id' => $id, 'total_down_time_ms' => 0, 'total_hold_ms' => 0, 'total_cycle_ms' => 250000, 'laps' => $this->laps($laps)], $s['operations']),
        ];
    }

    public function testTheCycleEndpointRequiresAuthentication()
    {
        $this->postJson('/api/v1/timeStudies/cycle', [])->assertStatus(401);
    }

    public function testACycleIsSavedAsLinkedStudiesOverHttp()
    {
        $s = $this->setupFor();

        $response = $this->postJson('/api/v1/timeStudies/cycle', $this->body($s), $s['headers'])->assertOk()->assertJsonPath('status', 'success');

        $this->assertCount(2, $response->json('data.studies'));
        $groupId = $response->json('data.cycle_group_id');
        $this->assertSame(2, DB::table('time_studies')->where('cycle_group_id', $groupId)->count());
    }

    public function testAnOperationThatCannotBeSavedRefusesTheWholeCycleWithAReadableReason()
    {
        $s = $this->setupFor();
        $body = $this->body($s);
        $body['operations'][1]['laps'] = [];

        $this->postJson('/api/v1/timeStudies/cycle', $body, $s['headers'])
            ->assertStatus(400)->assertJsonPath('status', 'error')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Operation 2') && str_contains($m, 'no laps'));
        $this->assertSame(0, DB::table('time_studies')->count());
    }

    public function testTheCycleReportRequiresAuthenticationAndListsASavedCycle()
    {
        $this->getJson('/api/v1/timeStudies/cycleReport')->assertStatus(401);

        $s = $this->setupFor();
        $this->postJson('/api/v1/timeStudies/cycle', $this->body($s), $s['headers'])->assertOk();

        $this->getJson('/api/v1/timeStudies/cycleReport', $s['headers'])->assertOk()
            ->assertJsonStructure(['data' => ['kpis' => ['cycles', 'avg_cycle_efficiency_pct'], 'cycles', 'comparison' => ['pairs']]])
            ->assertJsonPath('data.kpis.cycles', 1)
            ->assertJsonCount(2, 'data.cycles.0.operations');
    }

    public function testACycleOfOneOperationIsRefused()
    {
        $s = $this->setupFor();
        $body = $this->body($s);
        $body['operations'] = [$body['operations'][0]];

        $this->postJson('/api/v1/timeStudies/cycle', $body, $s['headers'])->assertStatus(400);
    }
}
