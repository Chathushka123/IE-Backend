<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Small, fully deterministic dataset for hand-verifying that the Skill Matrix
 * Insights calculation (SkillMatrixCalculationRepository::recalculate()) and
 * TeamPlanRepository::suggestSchedule() compute what they're supposed to —
 * every number below is chosen so it can be checked by hand, not eyeballed.
 *
 * Isolation: everything (down to shared reference data like departments/
 * product categories/operation grades) lives under a single "GAP-" prefixed
 * demo Factory, so recalculating the skill matrix here can never disturb a
 * real factory's "latest run" (SkillMatrixCalculationRun is latest-only per
 * factory scope).
 *
 * Safe to re-run: deletes any prior GAP-DEMO dataset first, then reinserts.
 * (time_studies has no natural unique key, so insertOrIgnore() can't be used
 * without risking silent duplication of the hand-computed aggregates.)
 *
 * Run: php artisan db:seed --class="Database\Seeders\GapAnalysisDatasetSeeder"
 *
 * ── Scenario reference (kept here as the single source of truth) ──────────
 *
 * Teams / suggestSchedule (operator_count / hourly_capacity / hours_needed):
 *   GAP-T1 Alpha  — fully staffed        — 6 / 180 / 10  (product GAP-P-A, qty 1800, total smv 1.20, target eff 60%)
 *   GAP-T2 Beta   — headcount gap        — 2 /  36 /  8  (product GAP-P-B, qty  288, total smv 2.00, target eff 60%)
 *   GAP-T3 Gamma  — quality/training gap — 4 /  60 /  6  (product GAP-P-C, qty  360, total smv 2.00, target eff 50%)
 *
 * Skill matrix cells (employee x operation -> mean / median / mode / stddev / selected):
 *   GAP-EMP-101 x GAP-OP-01 (mean)         88,90,92          -> mean 90.00, stddev 2.0000,  selected 90.00
 *   GAP-EMP-102 x GAP-OP-01 (mean)         70,80,90          -> mean 80.00, stddev 10.0000, selected 80.00
 *   GAP-EMP-104 x GAP-OP-01 (mean)         40,45,50          -> mean 45.00, stddev 5.0000,  selected 45.00
 *   GAP-EMP-101 x GAP-OP-02 (mean)         92,94,96          -> mean 94.00, stddev 2.0000,  selected 94.00
 *   GAP-EMP-101 x GAP-OP-06 (mean)         50,55,60          -> mean 55.00, stddev 5.0000,  selected 55.00
 *   GAP-EMP-102 x GAP-OP-03 (median)       50,60,100         -> mean 70.00, median 60.00,   selected 60.00
 *   GAP-EMP-201 x GAP-OP-04 (median)       65,70,90          -> mean 75.00, median 70.00,   selected 70.00
 *   GAP-EMP-202 x GAP-OP-04 (median)       54,66,78          -> mean 66.00, median 66.00,   selected 66.00
 *   GAP-EMP-301 x GAP-OP-05 (mode, bin=5)  62,63,64,80,95     -> mean 72.80, median 64.00, mode 63.00 (no fallback), selected 63.00
 *   GAP-EMP-302 x GAP-OP-05 (mode, bin=5)  60,70,80           -> mean 70.00, mode fallback to mean, selected 70.00
 *   GAP-EMP-203 x GAP-OP-07 (mean)         70,75,80          -> mean 75.00, stddev 5.0000,  selected 75.00
 *   (11 cells, 35 studies total)
 *
 * Deliberate gaps:
 *   - GAP-OP-07 (Final Inspection): only ever-studied operator is GAP-EMP-203,
 *     who is Resigned. The two Active Beta employees (GAP-EMP-201/202) have
 *     zero cells for it -> a genuine "zero qualified active operators" gap.
 *   - GAP-OP-08 (Elastic Waist Attach): zero time_studies rows at all, despite
 *     being in GAP-P-C's routing -> a "no historical skill data" gap.
 *   - GAP-OP-05 (Bartack): both Gamma operators' selected_efficiency (63.00,
 *     70.00) sit below its expected_top_level_efficiency (85) -> a staffed-
 *     but-below-target quality/training gap, not a headcount gap.
 */
class GapAnalysisDatasetSeeder extends Seeder
{
    private const FACTORY_CODE = 'GAP-DEMO';

    public function run(): void
    {
        DB::transaction(function () {
            $this->resetDemoData();

            $factoryId = $this->seedFactory();
            [$deptId, $sectionId] = $this->seedDeptAndSection();
            [$customerId, $seasonId, $productCategoryId] = $this->seedProductTaxonomy();
            $machineTypeId = $this->seedMachine();
            $baseOpCategoryId = $this->seedBaseOperationCategory();
            $gradeId = $this->seedOperationGrade();
            $mgmtHierarchyId = $this->seedManagementHierarchy();

            $operationIds = $this->seedOperations($baseOpCategoryId, $productCategoryId, $machineTypeId, $gradeId);
            $this->seedSoftSkillsAndTags($operationIds);

            $productIds = $this->seedProducts($productCategoryId, $customerId, $seasonId);
            $this->linkFactoryProducts($factoryId, $productIds);
            $this->seedProductOperations($productIds, $operationIds);

            $teamIds = $this->seedTeams($factoryId, $sectionId, $deptId);
            $employeeIds = $this->seedEmployees($factoryId, $mgmtHierarchyId, $teamIds);

            $this->seedTeamPlans($teamIds, $productIds);
            $this->seedTimeStudies($factoryId, $operationIds, $employeeIds);
        });

        $this->command?->info('Gap Analysis demo dataset seeded: 1 factory, 3 teams, 15 employees, 8 operations, 3 products, 35 time studies.');
    }

    /**
     * FK-safe child -> parent teardown of any prior GAP-DEMO dataset,
     * scoped strictly to demo-factory ids / GAP-prefixed codes so a real
     * factory's data is never touched.
     */
    private function resetDemoData(): void
    {
        $factoryId = DB::table('factories')->where('code', self::FACTORY_CODE)->value('id');

        if (!$factoryId) {
            return;
        }

        $teamIds = DB::table('teams')->where('factory_id', $factoryId)->pluck('id');
        $employeeIds = DB::table('employees')->where('factory_id', $factoryId)->pluck('id');
        $productIds = DB::table('products')->where('style_code', 'LIKE', 'GAP-P-%')->pluck('id');
        $operationIds = DB::table('operations')->where('code', 'LIKE', 'GAP-OP-%')->pluck('id');

        DB::table('skill_matrix_calculation_runs')
            ->get(['id', 'factory_ids'])
            ->filter(fn ($run) => in_array($factoryId, (array) json_decode($run->factory_ids ?? '[]', true), true))
            ->pluck('id')
            ->each(fn ($id) => DB::table('skill_matrix_calculation_runs')->where('id', $id)->delete());

        DB::table('time_studies')->where('factory_id', $factoryId)->delete();
        DB::table('team_plans')->whereIn('team_id', $teamIds)->delete();
        DB::table('operation_skill')->whereIn('operation_id', $operationIds)->delete();
        DB::table('product_operations')->whereIn('product_id', $productIds)->delete();
        DB::table('factory_product')->where('factory_id', $factoryId)->delete();
        DB::table('employees')->whereIn('id', $employeeIds)->delete();
        DB::table('products')->whereIn('id', $productIds)->delete();
        DB::table('operations')->whereIn('id', $operationIds)->delete();
        DB::table('teams')->where('factory_id', $factoryId)->delete();
        DB::table('soft_skills')->where('code', 'LIKE', 'GAP-SK-%')->delete();

        DB::table('product_categories')->where('code', 'GAP-PC')->delete();
        DB::table('product_groups')->where('code', 'GAP-PG')->delete();
        DB::table('machine_types')->where('code', 'GAP-MT')->delete();
        DB::table('machine_categories')->where('code', 'GAP-MC')->delete();
        DB::table('base_operations')->where('code', 'LIKE', 'GAP-BO-%')->delete();
        DB::table('base_operation_categories')->where('code', 'GAP-BOC')->delete();
        DB::table('operation_grades')->where('code', 'GAP-GR')->delete();
        DB::table('management_hierarchies')->where('code', 'GAP-MH-OP')->delete();
        DB::table('seasons')->where('code', 'GAP-SEASON')->delete();
        DB::table('customers')->where('code', 'GAP-CUST')->delete();
        DB::table('sections')->where('code', 'GAP-SEC')->delete();
        DB::table('departments')->where('code', 'GAP-DEPT')->delete();

        DB::table('factories')->where('code', self::FACTORY_CODE)->delete();
    }

    private function seedFactory(): int
    {
        return DB::table('factories')->insertGetId([
            'name' => 'Gap Analysis Demo Factory',
            'code' => self::FACTORY_CODE,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{0: int, 1: int} [departmentId, sectionId] */
    private function seedDeptAndSection(): array
    {
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'Gap Analysis Demo Department',
            'code' => 'GAP-DEPT',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sectionId = DB::table('sections')->insertGetId([
            'name' => 'Gap Analysis Demo Section',
            'code' => 'GAP-SEC',
            'department_id' => $deptId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$deptId, $sectionId];
    }

    /** @return array{0: int, 1: int, 2: int} [customerId, seasonId, productCategoryId] */
    private function seedProductTaxonomy(): array
    {
        $customerId = DB::table('customers')->insertGetId([
            'description' => 'Gap Demo Customer',
            'code' => 'GAP-CUST',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $seasonId = DB::table('seasons')->insertGetId([
            'customer_id' => $customerId,
            'name' => 'Gap Demo Season',
            'code' => 'GAP-SEASON',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productGroupId = DB::table('product_groups')->insertGetId([
            'name' => 'Gap Demo Knits',
            'code' => 'GAP-PG',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productCategoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Gap Demo T-Shirts',
            'code' => 'GAP-PC',
            'product_group_id' => $productGroupId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$customerId, $seasonId, $productCategoryId];
    }

    private function seedMachine(): int
    {
        $machineCategoryId = DB::table('machine_categories')->insertGetId([
            'name' => 'Gap Demo Machines',
            'code' => 'GAP-MC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('machine_types')->insertGetId([
            'name' => 'Gap Demo Single Needle',
            'code' => 'GAP-MT',
            'machine_category_id' => $machineCategoryId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedBaseOperationCategory(): int
    {
        return DB::table('base_operation_categories')->insertGetId([
            'name' => 'Gap Demo Sewing Ops',
            'code' => 'GAP-BOC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedOperationGrade(): int
    {
        return DB::table('operation_grades')->insertGetId([
            'name' => 'Gap Demo Standard Grade',
            'code' => 'GAP-GR',
            'level' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedManagementHierarchy(): int
    {
        return DB::table('management_hierarchies')->insertGetId([
            'name' => 'Gap Demo Operator',
            'code' => 'GAP-MH-OP',
            'seq_no' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, int> operation code => id */
    private function seedOperations(int $baseOpCategoryId, int $productCategoryId, int $machineTypeId, int $gradeId): array
    {
        $defs = [
            // code          description              smv    method    bin  top upperMid lowerMid
            ['GAP-OP-01', 'Shoulder Join',           0.30, 'mean',   1, 85, 70, 55],
            ['GAP-OP-02', 'Side Seam',                0.30, 'mean',   1, 88, 72, 58],
            ['GAP-OP-03', 'Sleeve Attach',             0.30, 'median', 1, 80, 65, 50],
            ['GAP-OP-04', 'Neck Hem',                  0.50, 'median', 1, 85, 70, 55],
            ['GAP-OP-05', 'Bartack',                   0.50, 'mode',   5, 85, 70, 55],
            ['GAP-OP-06', 'Label Attach',              0.30, 'mean',   1, 90, 75, 60],
            ['GAP-OP-07', 'Final Inspection',          0.50, 'mean',   1, 85, 70, 55],
            ['GAP-OP-08', 'Elastic Waist Attach',      0.50, 'mode',   1, 85, 70, 55],
        ];

        $baseOpIds = [];
        foreach ($defs as $i => [$code]) {
            $baseOpIds[$code] = DB::table('base_operations')->insertGetId([
                'name' => 'Base ' . $code,
                'code' => 'GAP-BO-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'base_operation_category_id' => $baseOpCategoryId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $operationIds = [];
        foreach ($defs as [$code, $description, $smv, $method, $bin, $top, $upperMid, $lowerMid]) {
            $operationIds[$code] = DB::table('operations')->insertGetId([
                'base_operation_id' => $baseOpIds[$code],
                'product_category_id' => $productCategoryId,
                'machine_type_id' => $machineTypeId,
                'description' => $description,
                'code' => $code,
                'grade_id' => $gradeId,
                'smv' => $smv,
                'expected_top_level_efficiency' => $top,
                'expected_upper_mid_level_efficiency' => $upperMid,
                'expected_lower_mid_level_efficiency' => $lowerMid,
                'calculation_method' => $method,
                'mode_bin_size_pct' => $bin,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $operationIds;
    }

    private function seedSoftSkillsAndTags(array $operationIds): void
    {
        $detailId = DB::table('soft_skills')->insertGetId([
            'name' => 'Attention to Detail',
            'code' => 'GAP-SK-01',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $precisionId = DB::table('soft_skills')->insertGetId([
            'name' => 'Fine Motor Precision',
            'code' => 'GAP-SK-02',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('operation_skill')->insert([
            [
                'operation_id' => $operationIds['GAP-OP-07'],
                'soft_skill_id' => $detailId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'operation_id' => $operationIds['GAP-OP-05'],
                'soft_skill_id' => $precisionId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /** @return array<string, int> style_code => product id */
    private function seedProducts(int $productCategoryId, int $customerId, int $seasonId): array
    {
        $defs = [
            ['GAP-P-A', 'Gap Demo Tee Classic'],
            ['GAP-P-B', 'Gap Demo Tee Pocket'],
            ['GAP-P-C', 'Gap Demo Tee Raglan'],
        ];

        $productIds = [];
        foreach ($defs as [$styleCode, $name]) {
            $productIds[$styleCode] = DB::table('products')->insertGetId([
                'name' => $name,
                'style_code' => $styleCode,
                'product_category_id' => $productCategoryId,
                'customer_id' => $customerId,
                'season_id' => $seasonId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $productIds;
    }

    private function linkFactoryProducts(int $factoryId, array $productIds): void
    {
        DB::table('factory_product')->insert(array_map(
            fn ($productId) => ['factory_id' => $factoryId, 'product_id' => $productId, 'created_at' => now(), 'updated_at' => now()],
            array_values($productIds)
        ));
    }

    private function seedProductOperations(array $productIds, array $operationIds): void
    {
        // product style_code => [ [operation code, smv], ... ] in routing order
        $routings = [
            'GAP-P-A' => [['GAP-OP-01', 0.30], ['GAP-OP-02', 0.30], ['GAP-OP-03', 0.30], ['GAP-OP-06', 0.30]], // total 1.20
            'GAP-P-B' => [['GAP-OP-01', 0.50], ['GAP-OP-04', 0.50], ['GAP-OP-07', 0.50], ['GAP-OP-06', 0.50]], // total 2.00
            'GAP-P-C' => [['GAP-OP-02', 0.50], ['GAP-OP-05', 0.50], ['GAP-OP-08', 0.50], ['GAP-OP-06', 0.50]], // total 2.00
        ];

        foreach ($routings as $styleCode => $ops) {
            foreach ($ops as $i => [$opCode, $smv]) {
                DB::table('product_operations')->insert([
                    'product_id' => $productIds[$styleCode],
                    'operation_id' => $operationIds[$opCode],
                    'sequence_no' => $i + 1,
                    'smv' => $smv,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** @return array<string, int> team code => id */
    private function seedTeams(int $factoryId, int $sectionId, int $deptId): array
    {
        $defs = [
            ['GAP-T1', 'Gap Demo Team Alpha', 60.00],
            ['GAP-T2', 'Gap Demo Team Beta', 60.00],
            ['GAP-T3', 'Gap Demo Team Gamma', 50.00],
        ];

        $teamIds = [];
        foreach ($defs as [$code, $name, $targetEfficiency]) {
            $teamIds[$code] = DB::table('teams')->insertGetId([
                'name' => $name,
                'code' => $code,
                'section_id' => $sectionId,
                'department_id' => $deptId,
                'factory_id' => $factoryId,
                'is_active' => true,
                'working_minutes_per_day' => 480,
                'target_efficiency_pct' => $targetEfficiency,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $teamIds;
    }

    /** @return array<string, int> employee_no => id */
    private function seedEmployees(int $factoryId, int $mgmtHierarchyId, array $teamIds): array
    {
        // employee_no, team code, status, first name (persona label)
        $defs = [
            ['GAP-EMP-101', 'GAP-T1', 'Active', 'Alpha-Expert-1'],
            ['GAP-EMP-102', 'GAP-T1', 'Active', 'Alpha-AllRounder-1'],
            ['GAP-EMP-103', 'GAP-T1', 'Active', 'Alpha-Headcount-1'],
            ['GAP-EMP-104', 'GAP-T1', 'Active', 'Alpha-Trainee-1'],
            ['GAP-EMP-105', 'GAP-T1', 'Active', 'Alpha-Headcount-2'],
            ['GAP-EMP-106', 'GAP-T1', 'Active', 'Alpha-Headcount-3'],
            ['GAP-EMP-107', 'GAP-T1', 'Terminated', 'Alpha-Padding-1'],
            ['GAP-EMP-201', 'GAP-T2', 'Active', 'Beta-NoInspection-1'],
            ['GAP-EMP-202', 'GAP-T2', 'Active', 'Beta-NoInspection-2'],
            ['GAP-EMP-203', 'GAP-T2', 'Resigned', 'Beta-ExInspector-1'],
            ['GAP-EMP-301', 'GAP-T3', 'Active', 'Gamma-BelowTarget-1'],
            ['GAP-EMP-302', 'GAP-T3', 'Active', 'Gamma-BelowTarget-2'],
            ['GAP-EMP-303', 'GAP-T3', 'Active', 'Gamma-Headcount-1'],
            ['GAP-EMP-304', 'GAP-T3', 'Active', 'Gamma-Headcount-2'],
            ['GAP-EMP-305', 'GAP-T3', 'Resigned', 'Gamma-Padding-1'],
        ];

        $employeeIds = [];
        foreach ($defs as [$employeeNo, $teamCode, $status, $firstName]) {
            $employeeIds[$employeeNo] = DB::table('employees')->insertGetId([
                'factory_id' => $factoryId,
                'employee_no' => $employeeNo,
                'identification_no' => str_replace('GAP-EMP-', 'GAP-NIC-', $employeeNo),
                'first_name' => $firstName,
                'last_name' => 'Demo',
                'management_hierarchy_id' => $mgmtHierarchyId,
                'team_id' => $teamIds[$teamCode],
                'employee_status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $employeeIds;
    }

    private function seedTeamPlans(array $teamIds, array $productIds): void
    {
        $defs = [
            // team code, product style_code, planned_quantity, notes
            ['GAP-T1', 'GAP-P-A', 1800, 'Fully staffed line — every routed operation has qualified active operators.'],
            ['GAP-T2', 'GAP-P-B', 288, 'Headcount-constrained line — Final Inspection (GAP-OP-07) has zero active qualified operators.'],
            ['GAP-T3', 'GAP-P-C', 360, 'Staffed line, quality gap — nobody on Bartack (GAP-OP-05) clears the top efficiency band.'],
        ];

        foreach ($defs as [$teamCode, $styleCode, $qty, $notes]) {
            DB::table('team_plans')->insert([
                'team_id' => $teamIds[$teamCode],
                'product_id' => $productIds[$styleCode],
                'sequence_no' => 1,
                'planned_quantity' => $qty,
                'planned_start_date' => '2026-01-05',
                'planned_end_date' => '2026-01-09',
                'status' => 'planned',
                'notes' => $notes,
                'is_changeover' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedTimeStudies(int $factoryId, array $operationIds, array $employeeIds): void
    {
        // employee_no, operation code, [efficiency_pct, ...]
        $cells = [
            ['GAP-EMP-101', 'GAP-OP-01', [88, 90, 92]],
            ['GAP-EMP-102', 'GAP-OP-01', [70, 80, 90]],
            ['GAP-EMP-104', 'GAP-OP-01', [40, 45, 50]],
            ['GAP-EMP-101', 'GAP-OP-02', [92, 94, 96]],
            ['GAP-EMP-101', 'GAP-OP-06', [50, 55, 60]],
            ['GAP-EMP-102', 'GAP-OP-03', [50, 60, 100]],
            ['GAP-EMP-201', 'GAP-OP-04', [65, 70, 90]],
            ['GAP-EMP-202', 'GAP-OP-04', [54, 66, 78]],
            ['GAP-EMP-301', 'GAP-OP-05', [62, 63, 64, 80, 95]],
            ['GAP-EMP-302', 'GAP-OP-05', [60, 70, 80]],
            ['GAP-EMP-203', 'GAP-OP-07', [70, 75, 80]],
            // GAP-OP-08 intentionally has no rows at all — see class docblock.
        ];

        $rows = [];
        foreach ($cells as [$employeeNo, $opCode, $efficiencies]) {
            foreach ($efficiencies as $efficiency) {
                $rows[] = [
                    'factory_id' => $factoryId,
                    'study_date' => '2026-01-15',
                    'time_study_type' => 'production_floor',
                    'operation_id' => $operationIds[$opCode],
                    'employee_id' => $employeeIds[$employeeNo],
                    'efficiency_pct' => $efficiency,
                    'total_productive_ms' => 0,
                    'total_down_time_ms' => 0,
                    'total_hold_ms' => 0,
                    'total_cycle_ms' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('time_studies')->insert($rows);
    }
}
