<?php

namespace Tests\Unit\Repositories;

use App\Http\Repositories\GapAnalysisRepository;
use App\Operation;
use App\TeamPlan;
use Illuminate\Support\Facades\DB;

class GapAnalysisRepositoryTest extends RepositoryTestCase
{
    // ---------------------------------------------------------------
    // classifyCell() — pure, no DB
    // ---------------------------------------------------------------

    public function testClassifiesAsUnknownWhenRequiredOperatorsIsNull()
    {
        $this->assertEquals('unknown', GapAnalysisRepository::classifyCell(null, 3, true, true));
    }

    public function testClassifiesAsUnknownWhenThereIsNoHistoricalDataAtAllForTheOperation()
    {
        $this->assertEquals('unknown', GapAnalysisRepository::classifyCell(2, 0, false, false));
    }

    public function testClassifiesAsCriticalWhenZeroQualifiedActiveOperators()
    {
        $this->assertEquals('critical', GapAnalysisRepository::classifyCell(2, 0, false, true));
    }

    public function testClassifiesAsShortfallWhenSomeButNotEnoughQualifiedOperators()
    {
        $this->assertEquals('shortfall', GapAnalysisRepository::classifyCell(3, 1, false, true));
    }

    public function testClassifiesAsQualityGapWhenStaffedButNobodyClearsTheTopBand()
    {
        $this->assertEquals('quality_gap', GapAnalysisRepository::classifyCell(2, 2, false, true));
    }

    public function testClassifiesAsOkWhenStaffedAndSomeoneClearsTheTopBand()
    {
        $this->assertEquals('ok', GapAnalysisRepository::classifyCell(2, 2, true, true));
    }

    // ---------------------------------------------------------------
    // computeRequiredOperators()
    // ---------------------------------------------------------------

    private function makeTeamPlan(array $teamOverrides = [], array $planOverrides = []): TeamPlan
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 6));

        $sectionId = DB::table('sections')->insertGetId([
            'name' => 'Sewing '.$unique, 'code' => 'SEW'.$unique, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Production '.$unique, 'code' => 'PRD'.$unique, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $factoryId = DB::table('factories')->insertGetId([
            'name' => 'Plant '.$unique, 'code' => 'PL'.$unique, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $teamId = DB::table('teams')->insertGetId(array_merge([
            'name' => 'Line A', 'code' => 'LA'.$unique, 'section_id' => $sectionId, 'department_id' => $departmentId,
            'factory_id' => $factoryId, 'is_active' => true, 'working_minutes_per_day' => 480,
            'target_efficiency_pct' => 60, 'created_at' => now(), 'updated_at' => now(),
        ], $teamOverrides));

        $planId = DB::table('team_plans')->insertGetId(array_merge([
            'team_id' => $teamId, 'sequence_no' => 1, 'planned_quantity' => 1000,
            'planned_start_date' => '2026-01-01 00:00:00', 'planned_end_date' => '2026-01-01 10:00:00',
            'status' => 'planned', 'is_changeover' => false, 'created_at' => now(), 'updated_at' => now(),
        ], $planOverrides));

        return TeamPlan::with('team')->find($planId);
    }

    private function makeOperation(): Operation
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));

        $baseOperationCategoryId = DB::table('base_operation_categories')->insertGetId([
            'name' => 'Sewing Operations '.$unique, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $baseOperationId = DB::table('base_operations')->insertGetId([
            'name' => 'Stitch Sleeve '.$unique, 'code' => 'BO'.$unique,
            'base_operation_category_id' => $baseOperationCategoryId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $gradeId = DB::table('operation_grades')->insertGetId([
            'name' => 'Grade '.$unique, 'code' => 'OG'.$unique, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $productGroupId = DB::table('product_groups')->insertGetId([
            'name' => 'Knits '.$unique, 'code' => 'KN'.$unique, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $productCategoryId = DB::table('product_categories')->insertGetId([
            'name' => 'T-Shirts '.$unique, 'code' => 'TS'.$unique, 'product_group_id' => $productGroupId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $machineCategoryId = DB::table('machine_categories')->insertGetId([
            'name' => 'Sewing Machines '.$unique, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $machineTypeId = DB::table('machine_types')->insertGetId([
            'name' => 'Single Needle '.$unique, 'code' => 'MT'.$unique, 'machine_category_id' => $machineCategoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $operationId = DB::table('operations')->insertGetId([
            'code' => 'OP'.$unique, 'base_operation_id' => $baseOperationId, 'grade_id' => $gradeId,
            'product_category_id' => $productCategoryId, 'machine_type_id' => $machineTypeId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Operation::find($operationId);
    }

    public function testComputesRequiredOperatorsFromDurationCapacityAndQuantity()
    {
        // hourly_capacity_per_operator = (60 * 60%) / 0.5 = 72 pcs/hr; over 10h = 720;
        // required = ceil(1000 / 720) = 2
        $plan = $this->makeTeamPlan(['target_efficiency_pct' => 60], ['planned_quantity' => 1000]);
        $operation = $this->makeOperation();

        [$required, $reason] = GapAnalysisRepository::computeRequiredOperators($plan, $operation, 0.5);

        $this->assertEquals(2, $required);
        $this->assertNull($reason);
    }

    public function testReturnsMissingDatesReasonWhenPlanHasNoDates()
    {
        $plan = $this->makeTeamPlan([], ['planned_start_date' => null, 'planned_end_date' => null]);
        $operation = $this->makeOperation();

        [$required, $reason] = GapAnalysisRepository::computeRequiredOperators($plan, $operation, 0.5);

        $this->assertNull($required);
        $this->assertEquals('missing_dates', $reason);
    }

    public function testReturnsMissingQuantityReasonWhenPlanHasNoQuantity()
    {
        $plan = $this->makeTeamPlan([], ['planned_quantity' => null]);
        $operation = $this->makeOperation();

        [$required, $reason] = GapAnalysisRepository::computeRequiredOperators($plan, $operation, 0.5);

        $this->assertNull($required);
        $this->assertEquals('missing_quantity', $reason);
    }

    public function testReturnsMissingSmvReasonWhenSmvIsZero()
    {
        $plan = $this->makeTeamPlan();
        $operation = $this->makeOperation();

        [$required, $reason] = GapAnalysisRepository::computeRequiredOperators($plan, $operation, 0.0);

        $this->assertNull($required);
        $this->assertEquals('missing_smv', $reason);
    }

    public function testReturnsMissingTargetEfficiencyReasonWhenTeamHasNoTargetEfficiency()
    {
        $plan = $this->makeTeamPlan(['target_efficiency_pct' => 0]);
        $operation = $this->makeOperation();

        [$required, $reason] = GapAnalysisRepository::computeRequiredOperators($plan, $operation, 0.5);

        $this->assertNull($required);
        $this->assertEquals('missing_target_efficiency', $reason);
    }

    public function testReturnsInvalidDurationReasonWhenEndDateIsNotAfterStartDate()
    {
        $plan = $this->makeTeamPlan([], [
            'planned_start_date' => '2026-01-01 10:00:00',
            'planned_end_date' => '2026-01-01 10:00:00',
        ]);
        $operation = $this->makeOperation();

        [$required, $reason] = GapAnalysisRepository::computeRequiredOperators($plan, $operation, 0.5);

        $this->assertNull($required);
        $this->assertEquals('invalid_duration', $reason);
    }
}
