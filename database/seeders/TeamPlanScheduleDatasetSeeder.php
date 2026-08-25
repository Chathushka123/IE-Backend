<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Small, hand-verifiable dataset for the date-only Schedule Board and the
 * apparel order-milestone tracker (TeamPlanRepository::assertNoOverlap,
 * ProductMilestone). Mirrors GapAnalysisDatasetSeeder's isolation approach:
 * everything lives under its own "SCHED-" prefixed demo factory, so it can
 * never collide with or disturb a real factory's schedule.
 *
 * Safe to re-run: deletes any prior SCHED-DEMO dataset first, then reinserts.
 *
 * Run: php artisan db:seed --class="Database\Seeders\TeamPlanScheduleDatasetSeeder"
 *
 * ── Scenario reference (kept here as the single source of truth) ──────────
 *
 * Team Plans — two teams, three products, deliberately built to exercise
 * both halves of the inclusive-date-range overlap rule:
 *
 *   SCHED-T1: SCHED-P1 [09-01..09-05] -> SCHED-P3 [09-06..09-09]
 *             -> SCHED-P1 [09-10..09-15] -> SCHED-P3 [09-16..09-20]
 *   SCHED-T2: SCHED-P2 [09-01..09-08] -> SCHED-P2 [09-09..09-15]
 *             -> changeover [09-16..09-16]
 *
 *   Every adjacent pair above is back-to-back (one ends the day before the
 *   next starts) and is accepted by assertNoOverlap. The same team is also
 *   given the same product twice on non-consecutive ranges (SCHED-P1 and
 *   SCHED-P3 both run on SCHED-T1 twice), demonstrating that a product can
 *   have multiple disjoint bookings on one team.
 *
 *   Deliberate overlap-guard demonstration (NOT inserted — would be
 *   rejected by TeamPlanRepository::assertNoOverlap if attempted through the
 *   API): scheduling anything on SCHED-T1 that touches any day from 09-01 to
 *   09-20, or on SCHED-T2 for any day from 09-01 to 09-16, fails with "This
 *   team is already scheduled for ... from ... to ...". For example POSTing
 *   team_id=SCHED-T1, planned_start_date=2026-09-09, planned_end_date=
 *   2026-09-11 is rejected because it shares 09-09 with SCHED-P1's second run.
 *
 * Product Milestones — one row per product, staggered against "today" so
 * the planned-vs-actual split in the UI has something real to show:
 *
 *   SCHED-P1 — cutting already done, 1 day later than planned (a small PCD
 *              miss); nothing downstream of it has actuals yet.
 *   SCHED-P2 — fully planned only, zero actuals — an order that hasn't
 *              started at all.
 *   SCHED-P3 — cutting finished 4 days late — a larger PCD miss, useful for
 *              hand-checking a "PCD hit rate" style calculation later.
 */
class TeamPlanScheduleDatasetSeeder extends Seeder
{
    private const FACTORY_CODE = 'SCHED-DEMO';

    public function run(): void
    {
        DB::transaction(function () {
            $this->resetDemoData();

            $factoryId = $this->seedFactory();
            [$deptId, $sectionId] = $this->seedDeptAndSection();
            [$customerId, $seasonId, $productCategoryId] = $this->seedProductTaxonomy();

            $teamIds = $this->seedTeams($factoryId, $sectionId, $deptId);
            $productIds = $this->seedProducts($productCategoryId, $customerId, $seasonId);
            $this->linkFactoryProducts($factoryId, $productIds);

            $this->seedTeamPlans($teamIds, $productIds);
            $this->seedProductMilestones($productIds);
        });

        $this->command?->info('Team Plan Schedule demo dataset seeded: 1 factory, 2 teams, 3 products, 7 team plans, 3 milestone records.');
    }

    /**
     * FK-safe child -> parent teardown of any prior SCHED-DEMO dataset,
     * scoped strictly to demo-factory ids / SCHED-prefixed codes so a real
     * factory's schedule is never touched.
     */
    private function resetDemoData(): void
    {
        $factoryId = DB::table('factories')->where('code', self::FACTORY_CODE)->value('id');

        if (!$factoryId) {
            return;
        }

        // Matched by code, not factory_id — a demo team's factory can drift (e.g.
        // reassigned manually while testing the Teams admin page), same rationale
        // as matching products by style_code below rather than a factory_product link.
        $teamIds = DB::table('teams')->where('code', 'LIKE', 'SCHED-T%')->pluck('id');
        $productIds = DB::table('products')->where('style_code', 'LIKE', 'SCHED-P%')->pluck('id');

        // Also catches a team_plan on some other (non-demo) team that references one of
        // these demo products — e.g. picked manually while testing the Schedule Board —
        // which whereIn('team_id', $teamIds) alone wouldn't reach, and would otherwise
        // block deleting the product below via fk_team_plans_product.
        DB::table('team_plans')
            ->where(function ($q) use ($teamIds, $productIds) {
                $q->whereIn('team_id', $teamIds)->orWhereIn('product_id', $productIds);
            })
            ->delete();
        DB::table('product_milestones')->whereIn('product_id', $productIds)->delete();
        DB::table('products')->whereIn('id', $productIds)->delete();
        DB::table('teams')->whereIn('id', $teamIds)->delete();

        DB::table('product_categories')->where('code', 'SCHED-PC')->delete();
        DB::table('product_groups')->where('code', 'SCHED-PG')->delete();
        DB::table('seasons')->where('code', 'SCHED-SEASON')->delete();
        DB::table('customers')->where('code', 'SCHED-CUST')->delete();
        DB::table('sections')->where('code', 'SCHED-SEC')->delete();
        DB::table('departments')->where('code', 'SCHED-DEPT')->delete();

        DB::table('factories')->where('code', self::FACTORY_CODE)->delete();
    }

    private function seedFactory(): int
    {
        return DB::table('factories')->insertGetId([
            'name' => 'Team Plan Schedule Demo Factory',
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
            'name' => 'Schedule Demo Department',
            'code' => 'SCHED-DEPT',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sectionId = DB::table('sections')->insertGetId([
            'name' => 'Schedule Demo Section',
            'code' => 'SCHED-SEC',
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
            'description' => 'Schedule Demo Customer',
            'code' => 'SCHED-CUST',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $seasonId = DB::table('seasons')->insertGetId([
            'customer_id' => $customerId,
            'name' => 'Schedule Demo Season',
            'code' => 'SCHED-SEASON',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productGroupId = DB::table('product_groups')->insertGetId([
            'name' => 'Schedule Demo Group',
            'code' => 'SCHED-PG',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productCategoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Schedule Demo Category',
            'code' => 'SCHED-PC',
            'product_group_id' => $productGroupId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$customerId, $seasonId, $productCategoryId];
    }

    /** @return array<string, int> team code => id */
    private function seedTeams(int $factoryId, int $sectionId, int $deptId): array
    {
        $defs = [
            ['SCHED-T1', 'Schedule Demo Line 1', 70.00],
            ['SCHED-T2', 'Schedule Demo Line 2', 72.00],
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

    /** @return array<string, int> style_code => id */
    private function seedProducts(int $productCategoryId, int $customerId, int $seasonId): array
    {
        $defs = [
            ['SCHED-P1', 'Schedule Demo Style One'],
            ['SCHED-P2', 'Schedule Demo Style Two'],
            ['SCHED-P3', 'Schedule Demo Style Three'],
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

    /**
     * Product is factory-scoped (ScopedToFactories) — a product linked to zero
     * factories is invisible to any query touching it (direct or eager-loaded)
     * once a request resolves to a real, non-bypassed factory context, even
     * though the row itself exists.
     */
    private function linkFactoryProducts(int $factoryId, array $productIds): void
    {
        DB::table('factory_product')->insert(array_map(
            fn ($productId) => ['factory_id' => $factoryId, 'product_id' => $productId, 'created_at' => now(), 'updated_at' => now()],
            array_values($productIds)
        ));
    }

    private function seedTeamPlans(array $teamIds, array $productIds): void
    {
        // team code, product style_code (null = changeover), sequence_no, planned_quantity,
        // planned_start_date, planned_end_date, notes
        $defs = [
            ['SCHED-T1', 'SCHED-P1', 1, 1000, '2026-09-01', '2026-09-05', 'First run of Style One'],
            ['SCHED-T1', 'SCHED-P3', 2,  800, '2026-09-06', '2026-09-09', 'Style Three squeezed in back-to-back'],
            ['SCHED-T1', 'SCHED-P1', 3, 1200, '2026-09-10', '2026-09-15', 'Second run of Style One'],
            ['SCHED-T1', 'SCHED-P3', 4,  900, '2026-09-16', '2026-09-20', 'Second run of Style Three'],
            ['SCHED-T2', 'SCHED-P2', 1, 2000, '2026-09-01', '2026-09-08', 'First run of Style Two'],
            ['SCHED-T2', 'SCHED-P2', 2, 1800, '2026-09-09', '2026-09-15', 'Second run of Style Two'],
            ['SCHED-T2', null,       3, null,  '2026-09-16', '2026-09-16', 'Style changeover: prepping line for next order'],
        ];

        foreach ($defs as [$teamCode, $styleCode, $seq, $qty, $start, $end, $notes]) {
            DB::table('team_plans')->insert([
                'team_id' => $teamIds[$teamCode],
                'product_id' => $styleCode ? $productIds[$styleCode] : null,
                'sequence_no' => $seq,
                'planned_quantity' => $qty,
                'planned_start_date' => $start,
                'planned_end_date' => $end,
                'status' => 'planned',
                'notes' => $notes,
                'is_changeover' => $styleCode === null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedProductMilestones(array $productIds): void
    {
        // style_code, planned_quantity (sum of that style's team_plan segments), planned_cut_date,
        // actual_cut_date, planned_production_start_date, planned_production_end_date,
        // planned_finishing_date, planned_final_inspection_date, planned_ex_factory_date,
        // planned_cargo_received_date, planned_etd, planned_eta
        $defs = [
            ['SCHED-P1', 2200, '2026-08-20', '2026-08-21', '2026-09-01', '2026-09-15', '2026-09-18', '2026-09-20', '2026-09-22', '2026-09-23', '2026-09-25', '2026-10-15'],
            ['SCHED-P2', 3800, '2026-08-27', null,          '2026-09-01', '2026-09-15', '2026-09-17', '2026-09-19', '2026-09-21', '2026-09-22', '2026-09-24', '2026-10-12'],
            ['SCHED-P3', 1700, '2026-08-15', '2026-08-19', '2026-09-06', '2026-09-20', '2026-09-23', '2026-09-25', '2026-09-27', '2026-09-28', '2026-09-30', '2026-10-20'],
        ];

        foreach ($defs as [$styleCode, $plannedQty, $plannedCut, $actualCut, $psd, $ped, $fnd, $fri, $exf, $crd, $etd, $eta]) {
            DB::table('product_milestones')->insert([
                'product_id' => $productIds[$styleCode],
                'planned_quantity' => $plannedQty,
                'planned_cut_date' => $plannedCut,
                'actual_cut_date' => $actualCut,
                'planned_production_start_date' => $psd,
                'actual_production_start_date' => null,
                'planned_production_end_date' => $ped,
                'actual_production_end_date' => null,
                'planned_finishing_date' => $fnd,
                'actual_finishing_date' => null,
                'planned_final_inspection_date' => $fri,
                'actual_final_inspection_date' => null,
                'planned_ex_factory_date' => $exf,
                'actual_ex_factory_date' => null,
                'planned_cargo_received_date' => $crd,
                'actual_cargo_received_date' => null,
                'planned_etd' => $etd,
                'actual_etd' => null,
                'planned_eta' => $eta,
                'actual_eta' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
