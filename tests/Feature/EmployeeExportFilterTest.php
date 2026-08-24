<?php

namespace Tests\Feature;

use App\Exports\EmployeesExport;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Repositories\EmployeeRepository;
use Tests\TestCase;

/**
 * Proves EmployeesExport::collection() only applies the filters actually
 * given (empty filters = everyone, matching pre-filter-dialog behavior), and
 * that whereIn / date-range filters correctly narrow the result set — the
 * data behind the Employees page's Export Filters dialog.
 */
class EmployeeExportFilterTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): int
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Exporter',
            'email' => 'exporter@example.com',
            'password' => bcrypt('Password1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Auth::login(User::find($userId));

        return $userId;
    }

    private function makeManagementHierarchy(): int
    {
        return DB::table('management_hierarchies')->insertGetId([
            'name' => 'Machine Operator',
            'seq_no' => 1,
            'retirement_age' => 55,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeFactory(): int
    {
        return DB::table('factories')->insertGetId([
            'name' => 'Plant One',
            'code' => 'PL' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedEmployees(int $hierarchyId, int $factoryId): void
    {
        EmployeeRepository::createRec([
            'employee_no' => 'EMP001',
            'identification_no' => '199012345671',
            'first_name' => 'Amara',
            'last_name' => 'Silva',
            'gender' => 'Female',
            'birthday' => '1995-06-10',
            'management_hierarchy_id' => $hierarchyId,
            'factory_id' => $factoryId,
        ]);

        EmployeeRepository::createRec([
            'employee_no' => 'EMP002',
            'identification_no' => '199012345672',
            'first_name' => 'Kasun',
            'last_name' => 'Perera',
            'gender' => 'Male',
            'birthday' => '1988-02-20',
            'management_hierarchy_id' => $hierarchyId,
            'factory_id' => $factoryId,
        ]);

        EmployeeRepository::createRec([
            'employee_no' => 'EMP003',
            'identification_no' => '199012345673',
            'first_name' => 'Nimal',
            'last_name' => 'Fernando',
            'gender' => 'Male',
            'birthday' => '2001-11-05',
            'management_hierarchy_id' => $hierarchyId,
            'factory_id' => $factoryId,
        ]);
    }

    public function test_no_filters_returns_every_employee()
    {
        $this->actingAsUser();
        $this->seedEmployees($this->makeManagementHierarchy(), $this->makeFactory());

        $rows = (new EmployeesExport([]))->collection();

        $this->assertCount(3, $rows);
    }

    public function test_where_in_filter_narrows_to_matching_employees()
    {
        $this->actingAsUser();
        $this->seedEmployees($this->makeManagementHierarchy(), $this->makeFactory());

        $rows = (new EmployeesExport(['gender' => ['Female']]))->collection();

        $this->assertCount(1, $rows);
        $this->assertSame('EMP001', $rows->first()->employee_no);
    }

    public function test_multiple_values_in_one_where_in_filter_are_ored_together()
    {
        $this->actingAsUser();
        $this->seedEmployees($this->makeManagementHierarchy(), $this->makeFactory());

        $rows = (new EmployeesExport(['employee_no' => null, 'gender' => ['Female', 'Male']]))->collection();

        $this->assertCount(3, $rows);
    }

    public function test_date_range_filter_narrows_by_birthday()
    {
        $this->actingAsUser();
        $this->seedEmployees($this->makeManagementHierarchy(), $this->makeFactory());

        $rows = (new EmployeesExport([
            'birthday_from' => '1990-01-01',
            'birthday_to' => '1999-12-31',
        ]))->collection();

        $this->assertCount(1, $rows);
        $this->assertSame('EMP001', $rows->first()->employee_no);
    }

    public function test_combining_where_in_and_date_range_filters_ands_them_together()
    {
        $this->actingAsUser();
        $this->seedEmployees($this->makeManagementHierarchy(), $this->makeFactory());

        $rows = (new EmployeesExport([
            'gender' => ['Male'],
            'birthday_from' => '1990-01-01',
        ]))->collection();

        $this->assertCount(1, $rows);
        $this->assertSame('EMP003', $rows->first()->employee_no);
    }

    public function test_distinct_values_endpoint_returns_sorted_unique_nationalities_and_religions()
    {
        $userId = $this->actingAsUser();
        $factoryId = $this->makeFactory();
        $hierarchyId = $this->makeManagementHierarchy();

        DB::table('factory_user')->insert([
            'factory_id' => $factoryId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        EmployeeRepository::createRec([
            'employee_no' => 'EMP001',
            'identification_no' => '199012345671',
            'first_name' => 'Amara',
            'last_name' => 'Silva',
            'nationality' => 'Sri Lankan',
            'religion' => 'Buddhist',
            'management_hierarchy_id' => $hierarchyId,
            'factory_id' => $factoryId,
        ]);
        EmployeeRepository::createRec([
            'employee_no' => 'EMP002',
            'identification_no' => '199012345672',
            'first_name' => 'Kasun',
            'last_name' => 'Perera',
            'nationality' => 'Sri Lankan',
            'religion' => 'Christian',
            'management_hierarchy_id' => $hierarchyId,
            'factory_id' => $factoryId,
        ]);
        EmployeeRepository::createRec([
            'employee_no' => 'EMP003',
            'identification_no' => '199012345673',
            'first_name' => 'Nimal',
            'last_name' => 'Fernando',
            'nationality' => null,
            'religion' => null,
            'management_hierarchy_id' => $hierarchyId,
            'factory_id' => $factoryId,
        ]);

        $token = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser(User::find($userId));

        $response = $this->getJson('/api/v1/employees/distinct-values', [
            'Authorization' => "Bearer $token",
            'X-Factory-Ids' => (string) $factoryId,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertSame(['Sri Lankan'], array_values($data['nationalities']));
        $this->assertSame(['Buddhist', 'Christian'], array_values($data['religions']));
    }
}
