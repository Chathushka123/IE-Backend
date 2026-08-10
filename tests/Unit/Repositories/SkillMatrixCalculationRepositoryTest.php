<?php

namespace Tests\Unit\Repositories;

use App\Http\Repositories\SkillMatrixCalculationRepository;
use App\Support\FactoryContext;
use Illuminate\Support\Facades\DB;

class SkillMatrixCalculationRepositoryTest extends RepositoryTestCase
{
    // ---------------------------------------------------------------
    // median()
    // ---------------------------------------------------------------

    public function testMedianOfAnOddCountIsTheSortedMiddleValue()
    {
        $this->assertEquals(20.0, SkillMatrixCalculationRepository::median([30, 10, 20]));
    }

    public function testMedianOfAnEvenCountIsTheAverageOfTheTwoMiddleValues()
    {
        $this->assertEquals(25.0, SkillMatrixCalculationRepository::median([10, 40, 20, 30]));
    }

    public function testMedianOfASingleValueIsThatValue()
    {
        $this->assertEquals(42.0, SkillMatrixCalculationRepository::median([42]));
    }

    // ---------------------------------------------------------------
    // modeWithBinning()
    // ---------------------------------------------------------------

    public function testModeWithBinSizeOneFindsTheClearWinningBucket()
    {
        $result = SkillMatrixCalculationRepository::modeWithBinning([70, 70, 70, 75, 80], 1);

        $this->assertFalse($result['used_fallback']);
        $this->assertEquals(70.0, $result['value']);
    }

    public function testModeWithBinSizeTenFindsTheClearWinningBucketAndAveragesItsRealValues()
    {
        // floor(x/10): 72,74,78 -> bucket 7 (3 values); 61 -> bucket 6; 95 -> bucket 9
        $result = SkillMatrixCalculationRepository::modeWithBinning([72, 74, 78, 61, 95], 10);

        $this->assertFalse($result['used_fallback']);
        $this->assertEqualsWithDelta(74.6666667, $result['value'], 0.0001);
    }

    public function testModeFallsBackToMeanWhenEveryValueIsUnique()
    {
        $result = SkillMatrixCalculationRepository::modeWithBinning([10, 20, 30], 1);

        $this->assertTrue($result['used_fallback']);
        $this->assertNull($result['value']);
    }

    public function testModeFallsBackToMeanForASingleValueCell()
    {
        $result = SkillMatrixCalculationRepository::modeWithBinning([55], 1);

        $this->assertTrue($result['used_fallback']);
        $this->assertNull($result['value']);
    }

    public function testModeBreaksATieBetweenBucketsByTheLowestBucketIndex()
    {
        // bin size 10: [15,16] -> bucket 1 (2 values); [25,26] -> bucket 2 (2 values); tied, bucket 1 wins
        $result = SkillMatrixCalculationRepository::modeWithBinning([15, 16, 25, 26], 10);

        $this->assertFalse($result['used_fallback']);
        $this->assertEquals(15.5, $result['value']);
    }

    // ---------------------------------------------------------------
    // sampleStdDev()
    // ---------------------------------------------------------------

    public function testSampleStdDevIsNullWhenFewerThanTwoValues()
    {
        $this->assertNull(SkillMatrixCalculationRepository::sampleStdDev([]));
        $this->assertNull(SkillMatrixCalculationRepository::sampleStdDev([50]));
    }

    public function testSampleStdDevUsesNMinusOneDenominator()
    {
        // mean 4; squared diffs 4,0,4; /(3-1) = 4; sqrt(4) = 2
        $this->assertEqualsWithDelta(2.0, SkillMatrixCalculationRepository::sampleStdDev([2, 4, 6]), 0.0001);
    }

    // ---------------------------------------------------------------
    // Full pipeline: recalculate() against real operations/employees/time_studies
    // ---------------------------------------------------------------

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

    private function makeOperation(string $calculationMethod, int $modeBinSizePct): int
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

    private function insertTimeStudy(int $factoryId, int $operationId, int $employeeId, float $efficiencyPct): void
    {
        DB::table('time_studies')->insert([
            'factory_id' => $factoryId,
            'study_date' => '2026-01-01',
            'time_study_type' => 'production_floor',
            'operation_id' => $operationId,
            'employee_id' => $employeeId,
            'efficiency_pct' => $efficiencyPct,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function testRecalculateComputesStatsPerOperationsConfiguredMethod()
    {
        $user = $this->actingAsTestUser();

        $factoryOne = $this->makeFactory();
        $employeeOne = $this->makeEmployee($factoryOne);

        $meanOperation = $this->makeOperation('mean', 1);
        $medianOperation = $this->makeOperation('median', 1);
        $modeOperation = $this->makeOperation('mode', 1);

        foreach ([60, 70, 110] as $value) {
            $this->insertTimeStudy($factoryOne, $meanOperation, $employeeOne, $value);
        }
        foreach ([50, 55, 90] as $value) {
            $this->insertTimeStudy($factoryOne, $medianOperation, $employeeOne, $value);
        }
        foreach ([70, 70, 70, 75, 80] as $value) {
            $this->insertTimeStudy($factoryOne, $modeOperation, $employeeOne, $value);
        }

        FactoryContext::set([$factoryOne]);

        $run = SkillMatrixCalculationRepository::recalculate([], $user->id);

        $this->assertEquals([$factoryOne], $run->factory_ids);
        $this->assertEquals(11, $run->study_count_total);
        $this->assertEquals(3, $run->cell_count);

        $meanCell = DB::table('skill_matrix_calculation_cells')
            ->where('calculation_run_id', $run->id)->where('operation_id', $meanOperation)->first();
        $this->assertEquals(80.00, (float) $meanCell->mean_efficiency);
        $this->assertEquals(70.00, (float) $meanCell->median_efficiency);
        $this->assertEquals('mean', $meanCell->calculation_method_used);
        $this->assertEquals(80.00, (float) $meanCell->selected_efficiency);
        $this->assertEqualsWithDelta(26.4575, (float) $meanCell->stddev_efficiency, 0.001);

        $medianCell = DB::table('skill_matrix_calculation_cells')
            ->where('calculation_run_id', $run->id)->where('operation_id', $medianOperation)->first();
        $this->assertEquals(65.00, (float) $medianCell->mean_efficiency);
        $this->assertEquals(55.00, (float) $medianCell->median_efficiency);
        $this->assertEquals('median', $medianCell->calculation_method_used);
        $this->assertEquals(55.00, (float) $medianCell->selected_efficiency);

        $modeCell = DB::table('skill_matrix_calculation_cells')
            ->where('calculation_run_id', $run->id)->where('operation_id', $modeOperation)->first();
        $this->assertEquals(73.00, (float) $modeCell->mean_efficiency);
        $this->assertEquals(70.00, (float) $modeCell->mode_efficiency);
        $this->assertEquals(0, (int) $modeCell->mode_used_fallback_to_mean);
        $this->assertEquals('mode', $modeCell->calculation_method_used);
        $this->assertEquals(70.00, (float) $modeCell->selected_efficiency);
    }

    public function testRecalculateUnderADifferentFactoryScopeLeavesTheOtherScopesRunUntouchedButReplacesItsOwn()
    {
        $user = $this->actingAsTestUser();

        $factoryOne = $this->makeFactory();
        $factoryTwo = $this->makeFactory();
        $employeeOne = $this->makeEmployee($factoryOne);
        $employeeTwo = $this->makeEmployee($factoryTwo);
        $operationOne = $this->makeOperation('mean', 1);
        $operationTwo = $this->makeOperation('mean', 1);

        $this->insertTimeStudy($factoryOne, $operationOne, $employeeOne, 60);
        $this->insertTimeStudy($factoryOne, $operationOne, $employeeOne, 70);

        FactoryContext::set([$factoryOne]);
        $runOne = SkillMatrixCalculationRepository::recalculate([], $user->id);

        $this->insertTimeStudy($factoryTwo, $operationTwo, $employeeTwo, 40);
        $this->insertTimeStudy($factoryTwo, $operationTwo, $employeeTwo, 50);

        FactoryContext::set([$factoryTwo]);
        $runTwo = SkillMatrixCalculationRepository::recalculate([], $user->id);

        $this->assertNotEquals($runOne->id, $runTwo->id);
        // Scope one's run and cells are still exactly as they were.
        $this->assertDatabaseHas('skill_matrix_calculation_runs', ['id' => $runOne->id]);
        $this->assertEquals(1, DB::table('skill_matrix_calculation_cells')->where('calculation_run_id', $runOne->id)->count());

        // Recalculating the SAME scope (factory one) replaces its run — old
        // cells cascade-deleted, exactly one run left for that scope.
        FactoryContext::set([$factoryOne]);
        $this->insertTimeStudy($factoryOne, $operationOne, $employeeOne, 100);
        $runOneAgain = SkillMatrixCalculationRepository::recalculate([], $user->id);

        $this->assertNotEquals($runOne->id, $runOneAgain->id);
        $this->assertDatabaseMissing('skill_matrix_calculation_runs', ['id' => $runOne->id]);
        $this->assertEquals(0, DB::table('skill_matrix_calculation_cells')->where('calculation_run_id', $runOne->id)->count());

        $allRuns = DB::table('skill_matrix_calculation_runs')->get();
        $scopeOneRuns = $allRuns->filter(fn ($run) => json_decode($run->factory_ids, true) === [$factoryOne]);
        $this->assertCount(1, $scopeOneRuns);

        // Scope two's run is still untouched by the scope-one recalculation.
        $this->assertDatabaseHas('skill_matrix_calculation_runs', ['id' => $runTwo->id]);
        $this->assertEquals(1, DB::table('skill_matrix_calculation_cells')->where('calculation_run_id', $runTwo->id)->count());
    }
}
