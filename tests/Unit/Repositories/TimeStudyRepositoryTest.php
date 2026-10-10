<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\TimeStudyRepository;
use Illuminate\Support\Facades\DB;

class TimeStudyRepositoryTest extends RepositoryTestCase
{
    // ---------------------------------------------------------------
    // createRec() — lap exclusion
    // ---------------------------------------------------------------

    public function testStoresExcludedLapsWhileKeepingEveryLap()
    {
        $this->actingAsTestUser();
        $rec = $this->buildRec([
            $this->lap(1, 9000, true),
            $this->lap(2, 6000),
            $this->lap(3, 6200),
            $this->lap(4, 3000, true),
        ]);

        $study = TimeStudyRepository::createRec($rec);

        $laps = DB::table('time_study_laps')->where('time_study_id', $study->id)->orderBy('lap_no')->get();
        $this->assertCount(4, $laps);
        $this->assertEquals([1, 0, 0, 1], $laps->pluck('is_excluded')->map(fn ($v) => (int) $v)->all());
    }

    public function testTreatsLapsWithoutTheFlagAsIncluded()
    {
        $this->actingAsTestUser();
        $lap = $this->lap(1, 6000);
        unset($lap['is_excluded']);

        $study = TimeStudyRepository::createRec($this->buildRec([$lap]));

        $this->assertEquals(0, (int) DB::table('time_study_laps')->where('time_study_id', $study->id)->value('is_excluded'));
    }

    public function testRejectsAStudyWhereEveryLapIsExcluded()
    {
        $this->actingAsTestUser();
        $rec = $this->buildRec([$this->lap(1, 6000, true), $this->lap(2, 6100, true)]);

        try {
            TimeStudyRepository::createRec($rec);
            $this->fail('Expected a GeneralException when every lap is excluded.');
        } catch (GeneralException $e) {
            $this->assertStringContainsString('At least one lap must be included', $e->getMessage());
        }

        $this->assertEquals(0, DB::table('time_studies')->count());
        $this->assertEquals(0, DB::table('time_study_laps')->count());
    }

    // ---------------------------------------------------------------
    // createRec() — conducted by
    // ---------------------------------------------------------------

    public function testDefaultsTheConductorToTheLoggedInUser()
    {
        $user = $this->actingAsTestUser();

        $study = TimeStudyRepository::createRec($this->buildRec([$this->lap(1, 6000)]));

        $this->assertEquals($user->id, DB::table('time_studies')->where('id', $study->id)->value('conducted_by_id'));
    }

    public function testStoresTheSelectedConductor()
    {
        $this->actingAsTestUser();
        $conductorId = DB::table('users')->insertGetId([
            'name' => 'Conductor',
            'email' => 'conductor-'.uniqid().'@example.com',
            'password' => bcrypt('Password1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rec = $this->buildRec([$this->lap(1, 6000)]);
        $rec['conducted_by_id'] = $conductorId;

        $study = TimeStudyRepository::createRec($rec);

        $this->assertEquals($conductorId, DB::table('time_studies')->where('id', $study->id)->value('conducted_by_id'));
    }

    // ---------------------------------------------------------------
    // createRec() — style requirement by type
    // ---------------------------------------------------------------

    public function testRequiresAStyleForProductionFloorStudies()
    {
        $this->actingAsTestUser();
        $rec = $this->buildRec([$this->lap(1, 6000)]);
        $rec['time_study_type'] = 'production_floor';

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('style');

        TimeStudyRepository::createRec($rec);
    }

    public function testAllowsInterviewAndTrainingStudiesWithoutAStyle()
    {
        $this->actingAsTestUser();

        $study = TimeStudyRepository::createRec($this->buildRec([$this->lap(1, 6000)]));

        $this->assertNull(DB::table('time_studies')->where('id', $study->id)->value('style_id'));
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function lap(int $lapNo, int $netMs, bool $excluded = false): array
    {
        return [
            'lap_no' => $lapNo,
            'duration_ms' => $netMs,
            'down_time_ms' => 0,
            'net_ms' => $netMs,
            'is_excluded' => $excluded,
        ];
    }

    private function buildRec(array $laps): array
    {
        $factoryId = $this->makeFactory();

        return [
            'factory_id' => $factoryId,
            'study_date' => '2026-10-10',
            'time_study_type' => 'interview_training',
            'operation_id' => $this->makeOperation(),
            'employee_id' => $this->makeEmployee($factoryId),
            'smv' => 0.1,
            'total_productive_ms' => 24200,
            'total_down_time_ms' => 0,
            'total_hold_ms' => 0,
            'total_cycle_ms' => 24200,
            'avg_cycle_ms' => 6100,
            'fastest_cycle_ms' => 6000,
            'slowest_cycle_ms' => 6200,
            'efficiency_pct' => 98.36,
            'laps' => $laps,
        ];
    }

    private function makeFactory(): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 6));

        return DB::table('factories')->insertGetId([
            'name' => 'Plant '.$unique,
            'code' => 'PL'.$unique,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEmployee(int $factoryId): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));

        $managementHierarchyId = DB::table('management_hierarchies')->insertGetId([
            'name' => 'Operator '.$unique,
            'seq_no' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('employees')->insertGetId([
            'employee_no' => 'EMP-'.$unique,
            'identification_no' => 'NIC-'.$unique,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'management_hierarchy_id' => $managementHierarchyId,
            'factory_id' => $factoryId,
            'employee_status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeOperation(string $calculationMethod = 'mean', int $modeBinSizePct = 1): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));

        $baseOperationCategoryId = DB::table('base_operation_categories')->insertGetId([
            'name' => 'Sewing Operations '.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $baseOperationId = DB::table('base_operations')->insertGetId([
            'name' => 'Stitch Sleeve '.$unique,
            'code' => 'BO'.$unique,
            'base_operation_category_id' => $baseOperationCategoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $gradeId = DB::table('operation_grades')->insertGetId([
            'name' => 'Grade '.$unique,
            'code' => 'OG'.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productGroupId = DB::table('product_groups')->insertGetId([
            'name' => 'Knits '.$unique,
            'code' => 'KN'.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productCategoryId = DB::table('product_categories')->insertGetId([
            'name' => 'T-Shirts '.$unique,
            'code' => 'TS'.$unique,
            'product_group_id' => $productGroupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $machineCategoryId = DB::table('machine_categories')->insertGetId([
            'name' => 'Sewing Machines '.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $machineTypeId = DB::table('machine_types')->insertGetId([
            'name' => 'Single Needle '.$unique,
            'code' => 'MT'.$unique,
            'machine_category_id' => $machineCategoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('operations')->insertGetId([
            'code' => 'OP'.$unique,
            'base_operation_id' => $baseOperationId,
            'grade_id' => $gradeId,
            'product_category_id' => $productCategoryId,
            'machine_type_id' => $machineTypeId,
            'calculation_method' => $calculationMethod,
            'mode_bin_size_pct' => $modeBinSizePct,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
