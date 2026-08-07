<?php

namespace Tests\Feature;

use App\Employee;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Repositories\EmployeeRepository;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Importer',
            'email' => 'importer@example.com',
            'password' => bcrypt('Password1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Auth::login(User::find($userId));
    }

    private function makeCategory(string $name = 'Machine Operator'): int
    {
        return DB::table('employee_categories')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeFactory(string $name = 'Plant One'): int
    {
        $unique = strtoupper(substr(md5(uniqid()), 0, 6));

        return DB::table('factories')->insertGetId([
            'name' => $name,
            'code' => 'PL'.$unique,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function baseRow(array $overrides = []): array
    {
        return array_merge([
            'employee_no' => 'EMP001',
            'identification_no' => '199012345678',
            'full_name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'Male',
            'birthday' => null,
            'email_address' => null,
            'contact_no' => null,
            'address' => null,
            'marital_status' => null,
            'category' => 'Machine Operator',
            'factory' => 'Plant One',
            'department' => null,
            'designation' => null,
            'joining_date' => null,
            'leaving_date' => null,
            'confirmation_date' => null,
            'employment_type' => null,
            'reporting_manager' => null,
            'production_line' => null,
            'base_line' => null,
            'employee_status' => null,
        ], $overrides);
    }

    public function test_creates_new_employee_from_valid_row()
    {
        $this->actingAsUser();
        $this->makeCategory();
        $this->makeFactory();

        $summary = EmployeeRepository::importRows(collect([$this->baseRow()]));

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['created']);
        $this->assertSame(0, $summary['updated']);
        $this->assertEmpty($summary['failed']);
        $this->assertDatabaseHas('employees', ['employee_no' => 'EMP001', 'last_name' => 'Doe']);
    }

    public function test_updates_existing_employee_matched_by_employee_no()
    {
        $this->actingAsUser();
        $this->makeCategory();
        $this->makeFactory();

        EmployeeRepository::importRows(collect([$this->baseRow()]));
        $summary = EmployeeRepository::importRows(collect([$this->baseRow(['last_name' => 'Smith'])]));

        $this->assertSame(0, $summary['created']);
        $this->assertSame(1, $summary['updated']);
        $this->assertEmpty($summary['failed']);
        $this->assertSame(1, Employee::where('employee_no', 'EMP001')->count());
        $this->assertDatabaseHas('employees', ['employee_no' => 'EMP001', 'last_name' => 'Smith']);
    }

    public function test_blank_optional_cell_does_not_clear_existing_value_on_update()
    {
        $this->actingAsUser();
        $this->makeCategory();
        $this->makeFactory();

        EmployeeRepository::importRows(collect([$this->baseRow(['contact_no' => '0711234567'])]));
        EmployeeRepository::importRows(collect([$this->baseRow(['contact_no' => null])]));

        $this->assertDatabaseHas('employees', ['employee_no' => 'EMP001', 'contact_no' => '0711234567']);
    }

    public function test_row_with_unknown_category_fails_without_aborting_other_rows()
    {
        $this->actingAsUser();
        $this->makeCategory();
        $this->makeFactory();

        $rows = collect([
            $this->baseRow(['category' => 'Does Not Exist']),
            $this->baseRow(['employee_no' => 'EMP002', 'identification_no' => '199099998888']),
        ]);

        $summary = EmployeeRepository::importRows($rows);

        $this->assertSame(1, $summary['created']);
        $this->assertCount(1, $summary['failed']);
        $this->assertSame(2, $summary['failed'][0]['row']);
        $this->assertStringContainsString('Category', $summary['failed'][0]['error']);
        $this->assertDatabaseMissing('employees', ['employee_no' => 'EMP001']);
        $this->assertDatabaseHas('employees', ['employee_no' => 'EMP002']);
    }

    public function test_duplicate_employee_no_within_file_is_rejected_after_the_first_row()
    {
        $this->actingAsUser();
        $this->makeCategory();
        $this->makeFactory();

        $rows = collect([
            $this->baseRow(),
            $this->baseRow(['identification_no' => '199099998888']),
        ]);

        $summary = EmployeeRepository::importRows($rows);

        $this->assertSame(1, $summary['created']);
        $this->assertCount(1, $summary['failed']);
        $this->assertStringContainsString('Duplicate', $summary['failed'][0]['error']);
        $this->assertSame(1, Employee::where('employee_no', 'EMP001')->count());
    }

    public function test_employee_no_is_normalized_to_uppercase()
    {
        $this->actingAsUser();
        $this->makeCategory();
        $this->makeFactory();

        $summary = EmployeeRepository::importRows(collect([$this->baseRow(['employee_no' => 'emp001'])]));

        $this->assertSame(1, $summary['created']);
        $this->assertDatabaseHas('employees', ['employee_no' => 'EMP001']);
        $this->assertDatabaseMissing('employees', ['employee_no' => 'emp001']);
    }

    public function test_numeric_contact_no_cell_is_coerced_to_string()
    {
        // Excel returns unformatted numeric-looking cells (e.g. phone numbers) as int/float,
        // not string — the importer must coerce these instead of letting them fail the
        // backend's `contact_no => string` validation rule.
        $this->actingAsUser();
        $this->makeCategory();
        $this->makeFactory();

        $summary = EmployeeRepository::importRows(collect([$this->baseRow(['contact_no' => 771234567])]));

        $this->assertSame(1, $summary['created']);
        $this->assertEmpty($summary['failed']);
        $this->assertDatabaseHas('employees', ['employee_no' => 'EMP001', 'contact_no' => '771234567']);
    }

    public function test_row_missing_employee_no_fails_with_readable_message()
    {
        $this->actingAsUser();
        $this->makeCategory();
        $this->makeFactory();

        $summary = EmployeeRepository::importRows(collect([$this->baseRow(['employee_no' => ''])]));

        $this->assertCount(1, $summary['failed']);
        $this->assertSame('Employee No is required', $summary['failed'][0]['error']);
    }
}
