<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\TimeStudyCycleRepository as Cycles;
use App\Http\Repositories\TimeStudyPlanRepository as Plans;
use App\Http\Repositories\TimeStudyRepository;
use App\Http\Repositories\TimeStudyTemplateRepository as Templates;
use App\Support\TimeStudy\TemplateDefinition;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProductFamilies;

class TimeStudyCycleRepositoryTest extends RepositoryTestCase
{
    public function testACycleIsSavedAsOneStudyPerOperationSharingAGroupAndKeepingTheirOrder()
    {
        $this->actingAsTestUser();
        $c = $this->context();

        $result = Cycles::create($this->cycleInput($c, [0, 1, 2]));

        $this->assertCount(3, $result['studies']);
        $this->assertSame([1, 2, 3], array_column($result['studies'], 'position'));
        $this->assertSame($c['operations'], array_column($result['studies'], 'operation_id'));
        $rows = DB::table('time_studies')->where('cycle_group_id', $result['cycle_group_id'])->orderBy('cycle_position')->get();
        $this->assertCount(3, $rows);
        $this->assertSame([1, 2, 3], $rows->pluck('cycle_position')->map(fn ($p) => (int) $p)->all());
        $this->assertCount(1, $rows->pluck('employee_id')->unique());
        $this->assertCount(1, $rows->pluck('study_date')->unique());
    }

    public function testEachOperationIsJudgedAgainstItsOwnSmvAndItsOwnLaps()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $input = $this->cycleInput($c, [0, 1]);
        // Same lap times on both operations, but the second operation's standard is twice as long.
        DB::table('operations')->where('id', $c['operations'][1])->update(['smv' => 1.0]);

        $result = Cycles::create($input);

        $efficiencies = DB::table('time_studies')->whereIn('id', array_column($result['studies'], 'id'))->orderBy('cycle_position')->pluck('efficiency_pct')->map(fn ($e) => (float) $e)->all();
        $this->assertEqualsWithDelta($efficiencies[0] * 2, $efficiencies[1], 0.5);
        $this->assertSame(8, DB::table('time_study_laps')->where('time_study_id', $result['studies'][0]['id'])->count());
    }

    public function testEveryStudyCountsInTheMatrixAndIsReviewedLikeAnyOther()
    {
        $this->actingAsTestUser();
        $c = $this->context();

        $result = Cycles::create($this->cycleInput($c, [0, 1]));

        $rows = DB::table('time_studies')->whereIn('id', array_column($result['studies'], 'id'))->get();
        $this->assertTrue($rows->every(fn ($r) => (bool) $r->counts_in_matrix && $r->purpose === 'perf'));
        $this->assertTrue($rows->every(fn ($r) => in_array($r->status, ['approved', 'needs_review'], true)));
    }

    public function testNothingIsSavedWhenOneOperationBreaksATemplateRule()
    {
        $this->actingAsTestUser();
        $c = $this->context(['min_laps_block' => 12]);
        $input = $this->cycleInput($c, [0, 1]);
        // The first operation has enough laps for the template's minimum of 12; the second has only five.
        $input['operations'][0]['laps'] = $this->laps(14);
        $input['operations'][1]['laps'] = $this->laps(5);

        try {
            Cycles::create($input);
            $this->fail('A study that breaks a blocking rule must refuse the whole cycle.');
        } catch (GeneralException $e) {
            $message = json_decode($e->getMessage(), true)['err'][0];
            $this->assertStringContainsString('Operation 2: This study does not meet the template rules', $message);
        }
        $this->assertSame(0, DB::table('time_studies')->count());
    }

    /** @dataProvider badOperationLists */
    public function testACycleNeedsBetweenTwoAndSixDistinctOperationsWithLaps(string $case)
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $input = $this->cycleInput($c, [0, 1]);

        match ($case) {
            'one operation' => $input['operations'] = [$input['operations'][0]],
            'none' => $input['operations'] = [],
            'duplicate' => $input['operations'][1]['operation_id'] = $input['operations'][0]['operation_id'],
            'seven operations' => $input['operations'] = array_fill(0, 7, $input['operations'][0]),
            'no laps' => $input['operations'][1]['laps'] = [],
        };

        $this->expectException(GeneralException::class);
        Cycles::create($input);
    }

    public static function badOperationLists(): array
    {
        return array_combine(['one operation', 'none', 'duplicate', 'seven operations', 'no laps'], array_map(fn ($c) => [$c], ['one operation', 'none', 'duplicate', 'seven operations', 'no laps']));
    }

    public function testEachOperationUsesTheTemplateTheServerWouldPickForIt()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $scoped = Templates::create(['name' => 'Only operation one '.uniqid(), 'purpose' => 'perf', 'conditions' => [['field' => 'operation', 'ref_id' => $c['operations'][0]]]]);
        Templates::publish($scoped['id']);
        $input = $this->cycleInput($c, [0, 1]);
        foreach ($input['operations'] as &$op) {
            unset($op['template_version_id']);
        }
        unset($op);

        $result = Cycles::create($input);

        $versions = DB::table('time_studies')->whereIn('id', array_column($result['studies'], 'id'))->orderBy('cycle_position')->pluck('template_version_id')->map(fn ($v) => (int) $v)->all();
        $this->assertSame(Templates::startFrom($scoped['id'])['version_id'], $versions[0]);
        $this->assertSame($c['versionId'], $versions[1]);
    }

    public function testACycleCanBeRecordedAgainstAPlansPairs()
    {
        $this->actingAsTestUser();
        $c = $this->context(['layout' => ['operations_per_operator' => 'combined']]);
        $plan = Plans::create(['name' => 'Cycle plan', 'factory_id' => $c['factory'], 'template_id' => $c['templateId'], 'time_study_type' => 'interview_training',
            'pairs' => [['operation_id' => $c['operations'][0], 'employee_id' => $c['employee']], ['operation_id' => $c['operations'][1], 'employee_id' => $c['employee']]]]);
        $plan = Plans::activate($plan['id']);
        $input = $this->cycleInput($c, [0, 1]);
        foreach ($plan['items'] as $i => $item) {
            $input['operations'][$i]['time_study_plan_item_id'] = $item['id'];
        }

        Cycles::create($input);

        $after = Plans::find($plan['id']);
        $this->assertTrue($after['items'][0]['done']);
        $this->assertTrue($after['items'][1]['done']);
        $this->assertSame(2, $after['progress']['pairs_done']);
    }

    public function testAPlanPairHeldByAnotherAnalystRefusesTheWholeCycle()
    {
        $this->actingAsTestUser();
        $c = $this->context(['layout' => ['operations_per_operator' => 'combined']]);
        $plan = Plans::create(['name' => 'Cycle plan', 'factory_id' => $c['factory'], 'template_id' => $c['templateId'], 'time_study_type' => 'interview_training',
            'pairs' => [['operation_id' => $c['operations'][0], 'employee_id' => $c['employee']], ['operation_id' => $c['operations'][1], 'employee_id' => $c['employee']]]]);
        $plan = Plans::activate($plan['id']);
        Plans::claim($plan['id'], $plan['items'][1]['id']);
        $input = $this->cycleInput($c, [0, 1]);
        foreach ($plan['items'] as $i => $item) {
            $input['operations'][$i]['time_study_plan_item_id'] = $item['id'];
        }

        $this->actingAsTestUser();

        try {
            Cycles::create($input);
            $this->fail('A pair someone else holds must refuse the cycle.');
        } catch (GeneralException $e) {
            $this->assertStringContainsString('timing this pair right now', $e->getMessage());
        }
        $this->assertSame(0, DB::table('time_studies')->count());
    }

    public function testTheClientCannotMakeUpAGroupOnAnOrdinaryStudy()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $study = $this->cycleInput($c, [0])['operations'][0] + [
            'factory_id' => $c['factory'], 'study_date' => now()->toDateString(), 'time_study_type' => 'interview_training', 'employee_id' => $c['employee'],
        ];

        $saved = TimeStudyRepository::createRec($study + ['cycle_group_id' => '11111111-1111-1111-1111-111111111111', 'cycle_position' => 3]);

        $this->assertNull($saved->fresh()->cycle_group_id);
        $this->assertNull($saved->fresh()->cycle_position);
    }

    public function testAnOrdinaryStudyIsNotPartOfAnyCycle()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $study = $this->cycleInput($c, [0])['operations'][0] + ['factory_id' => $c['factory'], 'study_date' => now()->toDateString(), 'time_study_type' => 'interview_training', 'employee_id' => $c['employee']];

        $saved = TimeStudyRepository::createRec($study);

        $this->assertNull($saved->fresh()->cycle_group_id);
    }

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    private function laps(int $count): array
    {
        $laps = [];
        for ($n = 1; $n <= $count; $n++) {
            $laps[] = ['lap_no' => $n, 'duration_ms' => 30000 + $n * 100, 'down_time_ms' => 0, 'net_ms' => 30000 + $n * 100, 'is_excluded' => false];
        }

        return $laps;
    }

    /** @param int[] $indexes which of the context's operations are in the cycle, in order */
    private function cycleInput(array $c, array $indexes): array
    {
        $operations = [];
        foreach ($indexes as $i) {
            $laps = [];
            for ($n = 1; $n <= 8; $n++) {
                $laps[] = ['lap_no' => $n, 'duration_ms' => 30000 + $n * 100, 'down_time_ms' => 0, 'net_ms' => 30000 + $n * 100, 'is_excluded' => false];
            }
            $operations[] = [
                'operation_id' => $c['operations'][$i], 'template_version_id' => $c['versionId'], 'total_down_time_ms' => 0, 'total_hold_ms' => 0,
                'total_cycle_ms' => array_sum(array_column($laps, 'duration_ms')), 'laps' => $laps,
            ];
        }

        return [
            'factory_id' => $c['factory'], 'study_date' => now()->toDateString(), 'time_study_type' => 'interview_training',
            'employee_id' => $c['employee'], 'operations' => $operations,
        ];
    }

    /** A factory, three operations (labelled Operation 1..3, SMV 0.5), one operator and a published template. */
    private function context(array $o = []): array
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $factory = DB::table('factories')->insertGetId(['name' => 'Plant '.$unique, 'code' => 'PL'.substr($unique, 0, 6), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $baseCategoryId = DB::table('base_operation_categories')->insertGetId(['name' => 'Cat '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $gradeId = DB::table('operation_grades')->insertGetId(['name' => 'Grade '.$unique, 'code' => 'OG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $groupId = DB::table('product_groups')->insertGetId(['name' => 'Group '.$unique, 'code' => 'PG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Category '.$unique, 'code' => 'PC'.$unique, 'product_family_id' => ProductFamilies::forGroup($groupId), 'created_at' => now(), 'updated_at' => now()]);
        $machineCategoryId = DB::table('machine_categories')->insertGetId(['name' => 'MC '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $machineId = DB::table('machine_types')->insertGetId(['name' => 'Machine '.$unique, 'code' => 'MT'.$unique, 'machine_category_id' => $machineCategoryId, 'created_at' => now(), 'updated_at' => now()]);

        $operations = [];
        foreach ([1, 2, 3] as $i) {
            $baseOperationId = DB::table('base_operations')->insertGetId(['name' => "Op {$i} {$unique}", 'code' => "BO{$i}{$unique}", 'base_operation_category_id' => $baseCategoryId, 'created_at' => now(), 'updated_at' => now()]);
            $operations[] = DB::table('operations')->insertGetId([
                'code' => "OP{$i}{$unique}", 'description' => "Operation {$i}", 'base_operation_id' => $baseOperationId, 'grade_id' => $gradeId, 'sequence_no' => $i,
                'product_category_id' => $categoryId, 'machine_type_id' => $machineId, 'smv' => 0.5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $hierarchyId = DB::table('management_hierarchies')->insertGetId(['name' => 'Operator '.$unique, 'seq_no' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $employee = DB::table('employees')->insertGetId([
            'employee_no' => 'EMP-'.$unique, 'identification_no' => 'NIC-'.$unique, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'management_hierarchy_id' => $hierarchyId, 'factory_id' => $factory, 'employee_status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $definition = TemplateDefinition::normalize(['layout' => $o['layout'] ?? []], 'perf');
        if (isset($o['min_laps_block'])) {
            $definition['rules']['min_laps'] = ['value' => $o['min_laps_block'], 'severity' => 'block'];
        }
        $created = Templates::create(['name' => 'Cycle template '.$unique, 'purpose' => 'perf', 'definition' => $definition]);
        Templates::publish($created['id']);

        return ['factory' => $factory, 'operations' => $operations, 'employee' => $employee, 'templateId' => $created['id'], 'versionId' => Templates::startFrom($created['id'])['version_id']];
    }
}
