<?php

namespace Tests\Feature;

use App\Employee;
use App\EmployeeFieldChange;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Repositories\EmployeeRepository;
use Tests\TestCase;

/**
 * Proves EmployeeRepository writes one EmployeeFieldChange row per tracked
 * field that actually changes (plus a baseline seeded at creation), with a
 * human-readable label snapshot for FK fields — the data behind the
 * "employee journey" timeline.
 */
class EmployeeJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(string $name = 'Journey Tester'): int
    {
        $userId = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
            'password' => bcrypt('Password1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Auth::login(User::find($userId));

        return $userId;
    }

    private function makeManagementHierarchy(string $name = 'Machine Operator'): int
    {
        return DB::table('management_hierarchies')->insertGetId([
            'name' => $name,
            'seq_no' => 1,
            'retirement_age' => 55,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeFactory(string $name = 'Plant One'): int
    {
        $unique = strtoupper(substr(md5(uniqid()), 0, 6));

        return DB::table('factories')->insertGetId([
            'name' => $name,
            'code' => 'PL' . $unique,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeDepartment(string $name = 'Sewing'): int
    {
        return DB::table('departments')->insertGetId([
            'name' => $name,
            'code' => 'DPT-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'employee_no' => 'EMP001',
            'identification_no' => '199012345678',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'management_hierarchy_id' => $this->makeManagementHierarchy(),
            'factory_id' => $this->makeFactory(),
        ], $overrides);
    }

    public function test_creating_an_employee_seeds_baseline_journey_rows_for_set_fields()
    {
        $this->actingAsUser();

        $employee = EmployeeRepository::createRec($this->basePayload([
            'marital_status' => 'Single',
        ]));

        $rows = EmployeeFieldChange::where('employee_id', $employee->id)->get()->keyBy('field');

        $this->assertNotNull($rows->get('factory_id'));
        $this->assertNull($rows->get('factory_id')->old_value);
        $this->assertSame((string) $employee->factory_id, $rows->get('factory_id')->new_value);
        $this->assertSame('Plant One', $rows->get('factory_id')->new_label);

        $this->assertNotNull($rows->get('management_hierarchy_id'));
        $this->assertNull($rows->get('management_hierarchy_id')->old_value);

        $this->assertNotNull($rows->get('marital_status'));
        $this->assertNull($rows->get('marital_status')->old_value);
        $this->assertSame('Single', $rows->get('marital_status')->new_value);
        // Plain enum fields have no separate FK to resolve — the label mirrors the raw value.
        $this->assertSame('Single', $rows->get('marital_status')->new_label);

        // Untouched/omitted tracked fields must not produce a row.
        $this->assertNull($rows->get('team_id'));
        $this->assertNull($rows->get('reporting_manager_id'));
    }

    public function test_updating_a_tracked_field_records_old_and_new_label()
    {
        $this->actingAsUser('Jane Approver');
        $departmentOld = $this->makeDepartment('Cutting');
        $departmentNew = $this->makeDepartment('Sewing');

        $employee = EmployeeRepository::createRec($this->basePayload([
            'department_id' => $departmentOld,
        ]));

        EmployeeRepository::updateRec($employee->id, array_merge(
            $employee->only(['employee_no', 'identification_no', 'first_name', 'last_name', 'management_hierarchy_id', 'factory_id']),
            [
                'department_id' => $departmentNew,
                'updated_at' => $employee->updated_at,
            ]
        ));

        $change = EmployeeFieldChange::where('employee_id', $employee->id)
            ->where('field', 'department_id')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($change);
        $this->assertSame((string) $departmentOld, $change->old_value);
        $this->assertSame((string) $departmentNew, $change->new_value);
        $this->assertSame('Cutting', $change->old_label);
        $this->assertSame('Sewing', $change->new_label);
        $this->assertSame('Jane Approver', $change->changed_by_name);
    }

    public function test_updating_an_untracked_field_writes_no_journey_row()
    {
        $this->actingAsUser();
        $employee = EmployeeRepository::createRec($this->basePayload());
        $beforeCount = EmployeeFieldChange::where('employee_id', $employee->id)->count();

        EmployeeRepository::updateRec($employee->id, array_merge(
            $employee->only(['employee_no', 'identification_no', 'first_name', 'last_name', 'management_hierarchy_id', 'factory_id']),
            [
                'contact_no' => '+94-771234567',
                'updated_at' => $employee->updated_at,
            ]
        ));

        $this->assertSame($beforeCount, EmployeeFieldChange::where('employee_id', $employee->id)->count());
    }

    public function test_journey_endpoint_returns_newest_first()
    {
        $userId = $this->actingAsUser();
        $employee = EmployeeRepository::createRec($this->basePayload());

        DB::table('factory_user')->insert([
            'factory_id' => $employee->factory_id,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        EmployeeRepository::updateRec($employee->id, array_merge(
            $employee->only(['employee_no', 'identification_no', 'first_name', 'last_name', 'management_hierarchy_id', 'factory_id']),
            [
                'marital_status' => 'Married',
                'updated_at' => $employee->updated_at,
            ]
        ));

        $token = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser(User::find($userId));

        $response = $this->getJson("/api/v1/employees/{$employee->id}/journey", [
            'Authorization' => "Bearer $token",
            'X-Factory-Ids' => (string) $employee->factory_id,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        // Most recent change (marital_status) must be first.
        $this->assertSame('marital_status', $data[0]['field']);
    }
}
