<?php

namespace Tests\Unit\Repositories;

use App\Http\Repositories\TimeStudyCycleReportRepository as CycleReport;
use App\Http\Repositories\TimeStudyCycleRepository as Cycles;
use App\Http\Repositories\TimeStudyPlanReportRepository as PlanReport;
use App\Http\Repositories\TimeStudyPlanRepository as Plans;
use App\Http\Repositories\TimeStudyRepository;
use App\Http\Repositories\TimeStudyTemplateRepository as Templates;
use App\Support\FactoryContext;
use App\Support\TimeStudy\TemplateDefinition;
use Illuminate\Support\Facades\DB;

class TimeStudyReportsTest extends RepositoryTestCase
{
    // ---------------------------------------------------------------
    // plan report
    // ---------------------------------------------------------------

    public function testAnEmptyPlanReportHasTheSameShapeAsAFullOne()
    {
        $this->actingAsTestUser();

        $report = PlanReport::report();

        $this->assertSame([], $report['plans']);
        $this->assertSame([], $report['analysts']);
        $this->assertSame(0, $report['kpis']['plans']);
        $this->assertNull($report['kpis']['completion_pct']);
    }

    public function testAPlanReportShowsProgressAgainstWhatWasPlanned()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c, [[0, 0], [0, 1], [1, 0], [1, 1]]);
        $this->record($c, $plan['items'][0]['id'], 0, 0);
        $this->record($c, $plan['items'][1]['id'], 0, 1);
        $this->record($c, $plan['items'][2]['id'], 1, 0);

        $row = PlanReport::report(['factory_ids' => [$c['factory']]])['plans'][0];

        $this->assertSame(4, $row['pairs']);
        $this->assertSame(3, $row['pairs_done']);
        $this->assertSame(4, $row['studies_target']);
        $this->assertSame(3, $row['studies_done']);
        $this->assertSame(75.0, $row['completion_pct']);
        $this->assertSame($plan['name'], $row['name']);
        $this->assertSame('active', $row['status']);
    }

    public function testEachOperationIsCheckedAgainstTheOperatorsTheTemplateAsksFor()
    {
        $this->actingAsTestUser();
        $c = $this->context(['layout' => ['operators_per_operation' => ['mode' => 'several', 'min' => 2], 'operations_per_operator' => 'several']]);
        $plan = $this->runningPlan($c, [[0, 0], [0, 1], [1, 0], [1, 1]]);
        // Operation 1 gets both operators done, operation 2 only one.
        foreach ([[0, 0, 0], [1, 0, 1], [2, 1, 0]] as [$item, $op, $emp]) {
            $this->record($c, $plan['items'][$item]['id'], $op, $emp);
        }

        $row = PlanReport::report(['factory_ids' => [$c['factory']]])['plans'][0];
        $byOperation = collect($row['operations'])->keyBy('operation_id');

        $this->assertTrue($byOperation[$c['operations'][0]]['met']);
        $this->assertFalse($byOperation[$c['operations'][1]]['met']);
        $this->assertSame(2, $byOperation[$c['operations'][1]]['required']);
        $this->assertSame(1, $byOperation[$c['operations'][1]]['operators_done']);
        $this->assertSame(2, $byOperation[$c['operations'][1]]['operators_planned']);
        $this->assertSame(1, $row['operations_unmet']);
    }

    public function testASkippedPairIsNotPlannedWorkAndIsCounted()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c, [[0, 0], [0, 1]]);
        Plans::skipPair($plan['id'], $plan['items'][1]['id'], 'Absent');

        $row = PlanReport::report(['factory_ids' => [$c['factory']]])['plans'][0];

        $this->assertSame(1, $row['pairs']);
        $this->assertSame(1, $row['skipped']);
        $this->assertSame(1, $row['studies_target']);
    }

    public function testARejectedStudyDoesNotCountSoThePairIsStillToDo()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c, [[0, 0]]);
        $study = $this->record($c, $plan['items'][0]['id'], 0, 0);
        DB::table('time_studies')->where('id', $study->id)->update(['status' => 'rejected']);

        $row = PlanReport::report(['factory_ids' => [$c['factory']]])['plans'][0];

        $this->assertSame(0, $row['pairs_done']);
        $this->assertSame(1, $row['rejected']);
        $this->assertSame(0.0, $row['completion_pct']);
    }

    public function testDraftsAndCancelledPlansAreCountedButNotJudgedOnProgress()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $running = $this->runningPlan($c, [[0, 0]]);
        $this->record($c, $running['items'][0]['id'], 0, 0);
        Plans::create($this->planInput($c, ['name' => 'A draft', 'pairs' => [$this->pair($c, 0, 0)]]));
        $cancelled = Plans::create($this->planInput($c, ['name' => 'Dropped', 'pairs' => [$this->pair($c, 0, 0)]]));
        Plans::cancel($cancelled['id']);

        $kpis = PlanReport::report(['factory_ids' => [$c['factory']]])['kpis'];

        $this->assertSame(3, $kpis['plans']);
        $this->assertSame([1, 1, 1], [$kpis['running'], $kpis['drafts'], $kpis['cancelled']]);
        $this->assertSame(1, $kpis['planned_studies']);
        $this->assertSame(100.0, $kpis['completion_pct']);
    }

    public function testADraftIsNotShortOfOperatorsBeforeAnyStudyCouldExist()
    {
        $this->actingAsTestUser();
        $c = $this->context(['layout' => ['operators_per_operation' => ['mode' => 'several', 'min' => 2], 'operations_per_operator' => 'several']]);
        Plans::create($this->planInput($c, ['pairs' => [$this->pair($c, 0, 0)]]));

        $row = PlanReport::report(['factory_ids' => [$c['factory']]])['plans'][0];

        $this->assertSame(0, $row['operations_unmet']);
        $this->assertFalse($row['operations'][0]['met']);
    }

    public function testTheReportSaysWhoRecordedTheStudiesAndHowManyNeedReview()
    {
        $me = $this->actingAsTestUser();
        $c = $this->context(['approval' => 'always']);
        $plan = $this->runningPlan($c, [[0, 0], [0, 1]]);
        $this->record($c, $plan['items'][0]['id'], 0, 0);
        $this->record($c, $plan['items'][1]['id'], 0, 1);

        $report = PlanReport::report(['factory_ids' => [$c['factory']]]);

        $this->assertSame($me->name, $report['analysts'][0]['name']);
        $this->assertSame(2, $report['analysts'][0]['studies']);
        $this->assertSame(2, $report['plans'][0]['needs_review']);
        $this->assertSame(2, $report['kpis']['needs_review']);
        $this->assertNotNull($report['plans'][0]['avg_quality']);
    }

    public function testPlansCanBeFilteredByStatusOperatorOperationAndFactory()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $one = $this->runningPlan($c, [[0, 0]], ['name' => 'Plan one']);
        $two = Plans::create($this->planInput($c, ['name' => 'Plan two', 'pairs' => [$this->pair($c, 1, 1)]]));
        $factory = ['factory_ids' => [$c['factory']]];

        $names = fn (array $filters) => collect(PlanReport::report($factory + $filters)['plans'])->pluck('name')->sort()->values()->all();

        $this->assertSame(['Plan one'], $names(['plan_statuses' => ['active']]));
        $this->assertSame(['Plan two'], $names(['employee_ids' => [$c['employees'][1]]]));
        $this->assertSame(['Plan one'], $names(['operation_ids' => [$c['operations'][0]]]));
        $this->assertSame(['Plan one', 'Plan two'], $names([]));
        $this->assertSame([], PlanReport::report(['factory_ids' => [999999]])['plans']);
        $this->assertNotNull($two);
    }

    public function testPlansAreFilteredByTheDateTheyStartedOrWereMade()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $old = Plans::create($this->planInput($c, ['name' => 'Old plan']));
        DB::table('time_study_plans')->where('id', $old['id'])->update(['created_at' => now()->subDays(40)]);
        Plans::create($this->planInput($c, ['name' => 'New plan']));

        $recent = PlanReport::report(['factory_ids' => [$c['factory']], 'study_date_from' => now()->subDays(7)->toDateString()])['plans'];
        $older = PlanReport::report(['factory_ids' => [$c['factory']], 'study_date_to' => now()->subDays(30)->toDateString()])['plans'];

        $this->assertSame(['New plan'], array_column($recent, 'name'));
        $this->assertSame(['Old plan'], array_column($older, 'name'));
    }

    public function testThePlanReportOnlyShowsTheUsersFactories()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        Plans::create($this->planInput($c, ['name' => 'Mine']));

        FactoryContext::set([999999]);
        $hidden = PlanReport::report();
        FactoryContext::set([$c['factory']]);
        $visible = PlanReport::report();
        FactoryContext::reset();

        $this->assertSame([], $hidden['plans']);
        $this->assertSame(['Mine'], array_column($visible['plans'], 'name'));
    }

    // ---------------------------------------------------------------
    // cycle report
    // ---------------------------------------------------------------

    public function testAnEmptyCycleReportHasTheSameShape()
    {
        $this->actingAsTestUser();

        $report = CycleReport::report();

        $this->assertSame([], $report['cycles']);
        $this->assertSame(0, $report['kpis']['cycles']);
        $this->assertSame(0, $report['comparison']['pairs']);
    }

    public function testACycleIsListedWithItsOperationsInOrderAndTheEfficiencyOfTheWholePass()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        // Operation 1: standard 0.5 min done in 30 s (100 %). Operation 2: standard 1.0 min done in 90 s (66.7 %).
        DB::table('operations')->where('id', $c['operations'][1])->update(['smv' => 1.0]);
        Cycles::create($this->cycleInput($c, [0, 1], [30000, 90000]));

        $report = CycleReport::report(['factory_ids' => [$c['factory']]]);
        $cycle = $report['cycles'][0];

        $this->assertCount(1, $report['cycles']);
        $this->assertSame([1, 2], array_column($cycle['operations'], 'position'));
        $this->assertSame($c['operations'], array_column($cycle['operations'], 'operation_id'));
        $this->assertSame(8, $cycle['rounds']);
        // (0.5 + 1.0) standard minutes per round over (0.5 + 1.5) actual minutes per round
        $this->assertEqualsWithDelta(75.0, $cycle['cycle_efficiency_pct'], 0.1);
        $this->assertEqualsWithDelta(100.0, $cycle['operations'][0]['efficiency_pct'], 0.1);
        $this->assertEqualsWithDelta(66.7, $cycle['operations'][1]['efficiency_pct'], 0.1);
        $this->assertSame(1, $report['kpis']['cycles']);
        $this->assertSame(2, $report['kpis']['studies']);
        $this->assertSame(2.0, $report['kpis']['avg_operations']);
    }

    public function testTimeSpentChangingOperationIsReportedPerRoundAndAsAShareOfTheTotal()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $reasonId = DB::table('time_study_downtime_reasons')->where('code', 'OPERATION_CHANGE')->value('id')
            ?? DB::table('time_study_downtime_reasons')->insertGetId(['code' => 'OPERATION_CHANGE', 'name' => 'Operation Change', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $input = $this->cycleInput($c, [0, 1], [30000, 30000]);
        foreach ($input['operations'] as &$op) {
            // Every lap carries 5 s of changing over, recorded as down time.
            foreach ($op['laps'] as &$lap) {
                $lap['duration_ms'] += 5000;
                $lap['down_time_ms'] = 5000;
            }
            unset($lap);
            $op['downtimes'] = array_map(fn ($lap) => ['lap_no' => $lap['lap_no'], 'time_study_downtime_reason_id' => $reasonId, 'duration_ms' => 5000], $op['laps']);
            $op['total_down_time_ms'] = 8 * 5000;
            $op['total_cycle_ms'] = array_sum(array_column($op['laps'], 'duration_ms'));
        }
        unset($op);
        Cycles::create($input);

        $cycle = CycleReport::report(['factory_ids' => [$c['factory']]])['cycles'][0];

        $this->assertSame(2 * 8 * 5000, $cycle['switch_ms']);
        $this->assertSame(10000, $cycle['switch_ms_per_round']);
        $this->assertEqualsWithDelta(14.3, $cycle['switch_share_pct'], 0.1); // 80 s of 560 s
    }

    public function testOrdinaryStudiesAreNotCycles()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $this->record($c, null, 0, 0);

        $this->assertSame([], CycleReport::report(['factory_ids' => [$c['factory']]])['cycles']);
    }

    public function testARejectedStudyStaysInTheListButLeavesTheAverages()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $result = Cycles::create($this->cycleInput($c, [0, 1], [30000, 60000]));
        DB::table('time_studies')->where('id', $result['studies'][1]['id'])->update(['status' => 'rejected']);

        $cycle = CycleReport::report(['factory_ids' => [$c['factory']]])['cycles'][0];

        $this->assertSame('rejected', $cycle['operations'][1]['status']);
        $this->assertSame(1, $cycle['rejected']);
        // Only the operation that was kept is judged: 0.5 min standard over 0.5 min actual.
        $this->assertEqualsWithDelta(100.0, $cycle['cycle_efficiency_pct'], 0.1);
    }

    public function testACycleRecordedFromAPlanNamesThePlanAndTemplate()
    {
        $this->actingAsTestUser();
        $c = $this->context(['layout' => ['operations_per_operator' => 'combined']]);
        $plan = $this->runningPlan($c, [[0, 0], [1, 0]], ['name' => 'Station plan']);
        $input = $this->cycleInput($c, [0, 1], [30000, 30000]);
        foreach ($plan['items'] as $i => $item) {
            $input['operations'][$i]['time_study_plan_item_id'] = $item['id'];
        }
        Cycles::create($input);

        $cycle = CycleReport::report(['factory_ids' => [$c['factory']]])['cycles'][0];

        $this->assertSame('Station plan', $cycle['plan_name']);
        $this->assertSame($plan['template']['name'], $cycle['template_name']);
    }

    public function testAnOperatorIsComparedInACycleAgainstTheSameOperationOnTheirOwn()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        // On their own: 60 s against a 0.5 min standard = 50 %. In a cycle: 30 s = 100 %.
        $this->record($c, null, 0, 0, 60000);
        Cycles::create($this->cycleInput($c, [0, 1], [30000, 30000]));

        $comparison = CycleReport::report(['factory_ids' => [$c['factory']]])['comparison'];

        $this->assertSame(1, $comparison['pairs']);
        $this->assertEqualsWithDelta(50.0, $comparison['avg_single_pct'], 0.1);
        $this->assertEqualsWithDelta(100.0, $comparison['avg_cycle_pct'], 0.1);
        $this->assertEqualsWithDelta(50.0, $comparison['difference_pts'], 0.1);
        $this->assertSame($c['operations'][0], $comparison['by_operation'][0]['operation_id']);
    }

    public function testOnlyPairsThatHaveBothKindsOfStudyAreCompared()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        Cycles::create($this->cycleInput($c, [0, 1], [30000, 30000]));

        $this->assertSame(0, CycleReport::report(['factory_ids' => [$c['factory']]])['comparison']['pairs']);
    }

    public function testCyclesCanBeFilteredByOperatorAndDate()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        Cycles::create($this->cycleInput($c, [0, 1], [30000, 30000]));
        $other = $this->cycleInput($c, [0, 1], [30000, 30000]);
        $other['employee_id'] = $c['employees'][1];
        $other['study_date'] = now()->subDays(30)->toDateString();
        Cycles::create($other);
        $factory = ['factory_ids' => [$c['factory']]];

        $byOperator = CycleReport::report($factory + ['employee_ids' => [$c['employees'][1]]])['cycles'];
        $recent = CycleReport::report($factory + ['study_date_from' => now()->subDays(7)->toDateString()])['cycles'];

        $this->assertSame([$c['employees'][1]], array_column($byOperator, 'employee_id'));
        $this->assertSame([$c['employees'][0]], array_column($recent, 'employee_id'));
    }

    public function testTheCycleReportOnlyShowsTheUsersFactories()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        Cycles::create($this->cycleInput($c, [0, 1], [30000, 30000]));

        FactoryContext::set([999999]);
        $hidden = CycleReport::report();
        FactoryContext::set([$c['factory']]);
        $visible = CycleReport::report();
        FactoryContext::reset();

        $this->assertSame([], $hidden['cycles']);
        $this->assertCount(1, $visible['cycles']);
    }

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    private function planInput(array $c, array $extra = []): array
    {
        return $extra + ['name' => 'Plan '.uniqid(), 'factory_id' => $c['factory'], 'template_id' => $c['templateId'], 'time_study_type' => 'interview_training', 'pairs' => []];
    }

    private function pair(array $c, int $operation, int $employee): array
    {
        return ['operation_id' => $c['operations'][$operation], 'employee_id' => $c['employees'][$employee]];
    }

    /** @param array<int,array{0:int,1:int}> $pairs [operation index, employee index] */
    private function runningPlan(array $c, array $pairs, array $extra = []): array
    {
        $plan = Plans::create($this->planInput($c, $extra + ['pairs' => array_map(fn ($p) => $this->pair($c, $p[0], $p[1]), $pairs)]));

        return Plans::activate($plan['id']);
    }

    private function laps(int $count, int $ms): array
    {
        $laps = [];
        for ($n = 1; $n <= $count; $n++) {
            $laps[] = ['lap_no' => $n, 'duration_ms' => $ms, 'down_time_ms' => 0, 'net_ms' => $ms, 'is_excluded' => false];
        }

        return $laps;
    }

    private function record(array $c, ?int $itemId, int $operation, int $employee, int $lapMs = 30000)
    {
        $laps = $this->laps(8, $lapMs);

        return TimeStudyRepository::createRec([
            'factory_id' => $c['factory'], 'study_date' => now()->toDateString(), 'time_study_type' => 'interview_training', 'operation_id' => $c['operations'][$operation],
            'employee_id' => $c['employees'][$employee], 'template_version_id' => $c['versionId'], 'time_study_plan_item_id' => $itemId, 'total_down_time_ms' => 0,
            'total_hold_ms' => 0, 'total_cycle_ms' => array_sum(array_column($laps, 'duration_ms')), 'laps' => $laps,
        ]);
    }

    /** @param int[] $indexes @param int[] $lapMs the lap time of each operation */
    private function cycleInput(array $c, array $indexes, array $lapMs): array
    {
        $operations = [];
        foreach ($indexes as $position => $i) {
            $laps = $this->laps(8, $lapMs[$position]);
            $operations[] = ['operation_id' => $c['operations'][$i], 'template_version_id' => $c['versionId'], 'total_down_time_ms' => 0, 'total_hold_ms' => 0, 'total_cycle_ms' => array_sum(array_column($laps, 'duration_ms')), 'laps' => $laps];
        }

        return ['factory_id' => $c['factory'], 'study_date' => now()->toDateString(), 'time_study_type' => 'interview_training', 'employee_id' => $c['employees'][0], 'operations' => $operations];
    }

    /** A factory, two operations (SMV 0.5), two operators and a published template. Options: layout, approval. */
    private function context(array $o = []): array
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $factory = DB::table('factories')->insertGetId(['name' => 'Plant '.$unique, 'code' => 'PL'.substr($unique, 0, 6), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
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
        $employees = [];
        foreach (['Jane', 'Joan'] as $i => $first) {
            $hierarchyId = DB::table('management_hierarchies')->insertGetId(['name' => 'Operator '.$unique.$i, 'seq_no' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $employees[] = DB::table('employees')->insertGetId([
                'employee_no' => 'EMP-'.$unique.$i, 'identification_no' => 'NIC-'.$unique.$i, 'first_name' => $first, 'last_name' => 'Doe',
                'management_hierarchy_id' => $hierarchyId, 'factory_id' => $factory, 'employee_status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $definition = TemplateDefinition::normalize(['layout' => $o['layout'] ?? [], 'approval' => $o['approval'] ?? 'warnings'], 'perf');
        $created = Templates::create(['name' => 'Report template '.$unique, 'purpose' => 'perf', 'definition' => $definition]);
        Templates::publish($created['id']);

        return ['factory' => $factory, 'operations' => $operations, 'employees' => $employees, 'templateId' => $created['id'], 'versionId' => Templates::startFrom($created['id'])['version_id']];
    }
}
