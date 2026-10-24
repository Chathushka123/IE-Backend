<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\OperationSmvRepository as Smv;
use App\Http\Repositories\SkillMatrixRepository;
use App\Http\Repositories\TimeStudyReviewRepository as Review;
use App\Http\Repositories\TimeStudySmvSessionRepository as Session;
use App\Support\FactoryContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class TimeStudySmvSessionRepositoryTest extends RepositoryTestCase
{
    // ---------------------------------------------------------------
    // saving a session
    // ---------------------------------------------------------------

    public function testSavesOneRowPerOperatorSharingASessionAndNeverCountingTowardsTheMatrix()
    {
        $this->actingAsTestUser();
        $ctx = $this->context();

        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 120)]));

        $rows = DB::table('time_studies')->where('session_id', $result['session_id'])->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame([1, 0], $rows->pluck('session_lead')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(['smv'], $rows->pluck('purpose')->unique()->values()->all());
        $this->assertSame([0], $rows->pluck('counts_in_matrix')->map(fn ($v) => (int) $v)->unique()->values()->all());
        $this->assertEquals([100.0, 120.0], $rows->pluck('rating_pct')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals($ctx['version'], $rows[0]->template_version_id);
    }

    public function testProposesTheMeanOfEachOperatorsRatedTimePlusTheAllowance()
    {
        $this->actingAsTestUser();
        $ctx = $this->context();

        // Both operators average 6 s (0.1 min). Ratings 100 and 120 give normal times 0.10 and 0.12; mean 0.11; plus 14 % = 0.1254.
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 120)]));

        $this->assertEqualsWithDelta(0.11, $result['proposal']['normal_minutes'], 1e-9);
        $this->assertEquals(0.1254, $result['proposal']['proposed_smv']);
        $this->assertEquals([0.1254], DB::table('time_studies')->where('session_id', $result['session_id'])->pluck('proposed_smv')->map(fn ($v) => (float) $v)->unique()->values()->all());
    }

    public function testTheProposalIsComparedWithTheOperationsCurrentSmv()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['operation_smv' => 0.1]);

        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 120)]));

        $this->assertEquals(0.1, $result['proposal']['current_smv']);
        $this->assertEquals(25.4, $result['proposal']['change_pct']);
        $this->assertEquals('operation', DB::table('time_studies')->where('session_id', $result['session_id'])->value('smv_source'));
    }

    public function testEveryRowIsCheckedByTheSameRulesAsAnyStudy()
    {
        $this->actingAsTestUser();
        $ctx = $this->context();

        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));

        $rows = DB::table('time_studies')->where('session_id', $result['session_id'])->get();
        $this->assertSame([6000, 6000], $rows->pluck('avg_cycle_ms')->map(fn ($v) => (int) $v)->all());
        $this->assertNotNull($rows[0]->quality_score);
        $this->assertCount(6, DB::table('time_study_laps')->where('time_study_id', $rows[0]->id)->get());
    }

    public function testTheSessionNeedsReviewWhenTheTemplateAlwaysAsksForIt()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['approval' => 'always']);

        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));

        $this->assertSame('needs_review', $result['status']);
        $this->assertSame(['needs_review'], DB::table('time_studies')->where('session_id', $result['session_id'])->pluck('status')->unique()->values()->all());
    }

    public function testAnApproveAutomaticallyTemplateApprovesACleanSession()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['approval' => 'never']);

        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));

        $this->assertSame('approved', $result['status']);
    }

    public function testOneOperatorsWarningSendsTheWholeSessionForReview()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['approval' => 'warnings', 'rules' => ['variation' => ['pct' => 20, 'severity' => 'warn']]]);
        $steady = $this->operatorRun($ctx['employees'][0], 100);
        $uneven = $this->operatorRun($ctx['employees'][1], 100, [3000, 6000, 6000, 9000, 6000, 6000]);

        $result = Session::create($this->payload($ctx, [$steady, $uneven]));

        $this->assertSame('needs_review', $result['status']);
        $flags = DB::table('time_studies')->where('session_id', $result['session_id'])->orderBy('id')->pluck('rule_flags')->map(fn ($f) => json_decode($f, true))->all();
        $this->assertSame([], $flags[0]);
        $this->assertSame('variation', $flags[1][0]['id']);
    }

    // ---------------------------------------------------------------
    // what blocks a session
    // ---------------------------------------------------------------

    public function testTooFewOperatorsBlocksTheSession()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['min_operators' => 3]);

        try {
            Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));
            $this->fail('Expected too few operators to block the session.');
        } catch (GeneralException $e) {
            $this->assertStringContainsString('Operators timed', $e->getMessage());
        }
        $this->assertSame(0, DB::table('time_studies')->count());
    }

    public function testAMissingRatingBlocksAndNamesTheOperator()
    {
        $this->actingAsTestUser();
        $ctx = $this->context();

        try {
            Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], null)]));
            $this->fail('Expected the missing rating to block the session.');
        } catch (GeneralException $e) {
            $this->assertStringContainsString('Jane Doe', $e->getMessage());
            $this->assertStringContainsString('Performance rating', $e->getMessage());
        }
        $this->assertSame(0, DB::table('time_studies')->count());
    }

    public function testARuleBlockingOneOperatorsRunStopsTheWholeSessionAndSavesNothing()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['rules' => ['min_laps' => ['value' => 10, 'severity' => 'block']]]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Laps');

        try {
            Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));
        } finally {
            $this->assertSame(0, DB::table('time_studies')->count());
        }
    }

    public function testARequiredQuestionMustBeAnswered()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['fields' => [['key' => 'fabric', 'label' => 'Fabric', 'type' => 'text', 'required' => true, 'options' => []]]]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Fabric');

        Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));
    }

    public function testAnOperatorCanBeTimedOnlyOncePerSession()
    {
        $this->actingAsTestUser();
        $ctx = $this->context();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('once per SMV study');

        Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][0], 110)]));
    }

    public function testEveryOperatorNeedsAnIncludedLap()
    {
        $this->actingAsTestUser();
        $ctx = $this->context();
        $allExcluded = $this->operatorRun($ctx['employees'][1], 100);
        $allExcluded['laps'] = array_map(fn ($l) => $l + ['is_excluded' => true], $allExcluded['laps']);
        $allExcluded['laps'] = array_map(fn ($l) => array_merge($l, ['is_excluded' => true]), $allExcluded['laps']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('at least one lap');

        Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $allExcluded]));
    }

    public function testATemplateForPerformanceStudiesCannotRunASession()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['purpose' => 'perf']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('not for establishing an SMV');

        Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));
    }

    public function testAnUnpublishedTemplateVersionCannotRunASession()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['status' => 'draft']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('not published');

        Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));
    }

    public function testASessionNeedsAtLeastOneOperator()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['min_operators' => 1]);

        $this->expectException(GeneralException::class);

        Session::create($this->payload($ctx, []));
    }

    // ---------------------------------------------------------------
    // reviewing a session
    // ---------------------------------------------------------------

    public function testOneDecisionCoversEveryOperatorsRow()
    {
        $reviewer = $this->actingAsTestUser();
        $ctx = $this->context();
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));

        // Decide through the second operator's row: it must still decide the whole session.
        Review::decide($result['study_ids'][1], 'approved', 'Good.');

        $rows = DB::table('time_studies')->where('session_id', $result['session_id'])->get();
        $this->assertSame(['approved'], $rows->pluck('status')->unique()->values()->all());
        $this->assertSame([$reviewer->id], $rows->pluck('reviewed_by_id')->unique()->values()->all());
        $this->assertSame(['Good.'], $rows->pluck('review_note')->unique()->values()->all());
    }

    public function testARejectedSessionStaysRejectedForEveryRow()
    {
        $this->actingAsTestUser();
        $ctx = $this->context();
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));
        Review::decide($result['study_ids'][0], 'rejected', 'Wrong operation timed.');

        try {
            Review::decide($result['study_ids'][1], 'approved', null);
            $this->fail('Expected the decision to be final.');
        } catch (GeneralException $e) {
            $this->assertStringContainsString('already been rejected', $e->getMessage());
        }
        $this->assertSame(['rejected'], DB::table('time_studies')->where('session_id', $result['session_id'])->pluck('status')->unique()->values()->all());
    }

    public function testAnApprovedSessionNeverReachesTheSkillMatrix()
    {
        $this->actingAsTestUser();
        $ctx = $this->context();
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));
        Review::decide($result['study_ids'][0], 'approved', null);
        FactoryContext::set([$ctx['factory']]);

        $this->assertCount(0, SkillMatrixRepository::getMatrix([])['cells']);
    }

    // ---------------------------------------------------------------
    // the proposal and applying it
    // ---------------------------------------------------------------

    public function testTheProposalListsEachOperatorsRunAndTheCurrentValues()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['operation_smv' => 0.1]);
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 120)]));

        $proposal = Smv::proposal($result['study_ids'][1]); // any row of the session

        $this->assertCount(2, $proposal['runs']);
        $this->assertSame('Jane Doe', $proposal['runs'][0]['employee_name']);
        $this->assertEqualsWithDelta(0.12, $proposal['runs'][1]['normal_minutes'], 1e-9);
        $this->assertSame(6, $proposal['runs'][0]['laps_counted']);
        $this->assertEquals(0.1254, $proposal['proposed_smv']);
        $this->assertEquals(0.1, $proposal['current']['operation_smv']);
        $this->assertSame('operation', $proposal['default_target']);
        $this->assertNull($proposal['applied']);
    }

    public function testTheDefaultTargetIsTheStyleOverrideWhenTheStyleHasOne()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['with_style' => 0.2]);
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));

        $proposal = Smv::proposal($result['study_ids'][0]);

        $this->assertSame('style_operation', $proposal['default_target']);
        $this->assertEquals(0.2, $proposal['current']['style_operation_smv']);
    }

    public function testApplyingChangesTheOperationsSmvAndRecordsTheOldOne()
    {
        $user = $this->actingAsTestUser();
        $ctx = $this->context(['operation_smv' => 0.1]);
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 120)]));
        Review::decide($result['study_ids'][0], 'approved', null);

        $proposal = Smv::apply($result['study_ids'][1], null);

        $this->assertEquals(0.1254, (float) DB::table('operations')->where('id', $ctx['operation'])->value('smv'));
        $history = DB::table('operation_smv_history')->where('source_session_id', $result['session_id'])->first();
        $this->assertEquals(0.1, (float) $history->old_smv);
        $this->assertEquals(0.1254, (float) $history->new_smv);
        $this->assertEquals($user->id, $history->applied_by_id);
        $this->assertNull($history->style_operation_id);
        $this->assertSame('operation', $proposal['applied']['target']);
    }

    public function testApplyingToTheStyleChangesOnlyTheStylesSmv()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['operation_smv' => 0.1, 'with_style' => 0.2]);
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 120)]));
        Review::decide($result['study_ids'][0], 'approved', null);

        Smv::apply($result['study_ids'][0], 'style_operation');

        $this->assertEquals(0.1254, (float) DB::table('style_operations')->where('id', $ctx['style_operation'])->value('smv'));
        $this->assertEquals(0.1, (float) DB::table('operations')->where('id', $ctx['operation'])->value('smv'));
        $this->assertEquals($ctx['style_operation'], DB::table('operation_smv_history')->value('style_operation_id'));
    }

    public function testTheReviewerCanChooseTheOperationEvenWhenTheStyleHasAnOverride()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['operation_smv' => 0.1, 'with_style' => 0.2]);
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));
        Review::decide($result['study_ids'][0], 'approved', null);

        Smv::apply($result['study_ids'][0], 'operation');

        $this->assertEquals(0.114, (float) DB::table('operations')->where('id', $ctx['operation'])->value('smv'));
        $this->assertEquals(0.2, (float) DB::table('style_operations')->where('id', $ctx['style_operation'])->value('smv'));
    }

    public function testAStudyThatIsNotApprovedCannotBeApplied()
    {
        $this->actingAsTestUser();
        $ctx = $this->context();
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Approve the study before applying');

        Smv::apply($result['study_ids'][0], null);
    }

    public function testARejectedStudyCannotBeApplied()
    {
        $this->actingAsTestUser();
        $ctx = $this->context();
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));
        Review::decide($result['study_ids'][0], 'rejected', 'No.');

        $this->expectException(GeneralException::class);

        Smv::apply($result['study_ids'][0], null);
    }

    public function testASessionCanBeAppliedOnlyOnce()
    {
        $this->actingAsTestUser();
        $ctx = $this->context(['operation_smv' => 0.1]);
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));
        Review::decide($result['study_ids'][0], 'approved', null);
        Smv::apply($result['study_ids'][0], null);

        try {
            Smv::apply($result['study_ids'][1], null);
            $this->fail('Expected a second application to be refused.');
        } catch (GeneralException $e) {
            $this->assertStringContainsString('already been applied', $e->getMessage());
        }
        $this->assertSame(1, DB::table('operation_smv_history')->count());
    }

    public function testAStyleTargetNeedsAStyleEntryAndUnknownTargetsAreRefused()
    {
        $this->actingAsTestUser();
        $ctx = $this->context();
        $result = Session::create($this->payload($ctx, [$this->operatorRun($ctx['employees'][0], 100), $this->operatorRun($ctx['employees'][1], 100)]));
        Review::decide($result['study_ids'][0], 'approved', null);

        foreach (['style_operation' => 'no entry for this operation', 'whatever' => 'Choose whether'] as $target => $message) {
            try {
                Smv::apply($result['study_ids'][0], $target);
                $this->fail("Expected '$target' to be refused.");
            } catch (GeneralException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
        $this->assertSame(0, DB::table('operation_smv_history')->count());
    }

    public function testAPerformanceStudyHasNoSmvProposal()
    {
        $this->actingAsTestUser();
        $ctx = $this->context();
        $studyId = DB::table('time_studies')->insertGetId([
            'factory_id' => $ctx['factory'], 'study_date' => '2026-10-20', 'time_study_type' => 'interview_training',
            'operation_id' => $ctx['operation'], 'employee_id' => $ctx['employees'][0], 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('not an SMV study');

        Smv::proposal($studyId);
    }

    public function testAnUnknownStudyIsNotFound()
    {
        $this->actingAsTestUser();

        $this->expectException(ModelNotFoundException::class);

        Smv::proposal(999999);
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function payload(array $ctx, array $runs, array $overrides = []): array
    {
        return $overrides + [
            'factory_id' => $ctx['factory'],
            'study_date' => '2026-10-20',
            'time_study_type' => $ctx['style'] ? 'production_floor' : 'interview_training',
            'operation_id' => $ctx['operation'],
            'style_id' => $ctx['style'],
            'template_version_id' => $ctx['version'],
            'field_values' => [],
            'runs' => $runs,
        ];
    }

    /** One operator's run: six laps of 6 s unless other net times are given. */
    private function operatorRun(int $employeeId, ?int $rating, ?array $nets = null): array
    {
        $nets = $nets ?? array_fill(0, 6, 6000);
        $laps = [];
        foreach ($nets as $i => $net) {
            $laps[] = ['lap_no' => $i + 1, 'duration_ms' => $net, 'down_time_ms' => 0, 'net_ms' => $net, 'is_excluded' => false];
        }

        return [
            'employee_id' => $employeeId,
            'rating_pct' => $rating,
            'total_down_time_ms' => 0,
            'total_hold_ms' => 0,
            'total_cycle_ms' => array_sum($nets),
            'laps' => $laps,
        ];
    }

    /**
     * Masters, two operators and a published Establish-SMV template.
     * Options: purpose, status, approval, min_operators, fields, rules (merged), operation_smv, with_style (a style override SMV).
     */
    private function context(array $o = []): array
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $factory = DB::table('factories')->insertGetId(['name' => 'Plant '.$unique, 'code' => 'PL'.substr($unique, 0, 6), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $baseCategoryId = DB::table('base_operation_categories')->insertGetId(['name' => 'Cat '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $baseOperationId = DB::table('base_operations')->insertGetId(['name' => 'Op '.$unique, 'code' => 'BO'.$unique, 'base_operation_category_id' => $baseCategoryId, 'created_at' => now(), 'updated_at' => now()]);
        $gradeId = DB::table('operation_grades')->insertGetId(['name' => 'Grade '.$unique, 'code' => 'OG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $groupId = DB::table('product_groups')->insertGetId(['name' => 'Group '.$unique, 'code' => 'PG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Category '.$unique, 'code' => 'PC'.$unique, 'product_group_id' => $groupId, 'created_at' => now(), 'updated_at' => now()]);
        $machineCategoryId = DB::table('machine_categories')->insertGetId(['name' => 'MC '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $machineId = DB::table('machine_types')->insertGetId(['name' => 'Machine '.$unique, 'code' => 'MT'.$unique, 'machine_category_id' => $machineCategoryId, 'created_at' => now(), 'updated_at' => now()]);
        $operation = DB::table('operations')->insertGetId([
            'code' => 'OP'.$unique, 'description' => 'Operation '.$unique, 'base_operation_id' => $baseOperationId, 'grade_id' => $gradeId,
            'product_category_id' => $categoryId, 'machine_type_id' => $machineId, 'smv' => $o['operation_smv'] ?? null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $employees = [];
        foreach (['Jane', 'Joan', 'June'] as $i => $first) {
            $hierarchyId = DB::table('management_hierarchies')->insertGetId(['name' => 'Operator '.$unique.$i, 'seq_no' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $employees[] = DB::table('employees')->insertGetId([
                'employee_no' => 'EMP-'.$unique.$i, 'identification_no' => 'NIC-'.$unique.$i, 'first_name' => $first, 'last_name' => 'Doe',
                'management_hierarchy_id' => $hierarchyId, 'factory_id' => $factory, 'employee_status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Names come from first + last name: the first two operators are "Jane Doe" and "Joan Doe".
        DB::table('employees')->where('id', $employees[1])->update(['first_name' => 'Jane']);

        $style = null;
        $styleOperation = null;
        if (isset($o['with_style'])) {
            $customerId = DB::table('customers')->insertGetId(['description' => 'Customer '.$unique, 'code' => 'CU'.$unique, 'created_at' => now(), 'updated_at' => now()]);
            $seasonId = DB::table('seasons')->insertGetId(['name' => 'Season '.$unique, 'code' => 'SE'.$unique, 'customer_id' => $customerId, 'created_at' => now(), 'updated_at' => now()]);
            $style = DB::table('styles')->insertGetId(['name' => 'Style '.$unique, 'style_code' => 'ST'.$unique, 'product_category_id' => $categoryId, 'customer_id' => $customerId, 'season_id' => $seasonId, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $styleOperation = DB::table('style_operations')->insertGetId(['style_id' => $style, 'operation_id' => $operation, 'sequence_no' => 1, 'smv' => $o['with_style'], 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        $purpose = $o['purpose'] ?? 'smv';
        $rules = array_merge([
            'min_laps' => ['value' => 4, 'severity' => 'off'],
            'outlier' => ['pct' => 30, 'severity' => 'off'],
            'variation' => ['pct' => 20, 'severity' => 'off'],
            'down_time' => ['pct' => 15, 'severity' => 'off'],
            'first_last' => 'ask',
            'min_operators' => ['value' => $o['min_operators'] ?? 2, 'severity' => 'block'],
            'rating_required' => true,
        ], $o['rules'] ?? []);
        $templateId = DB::table('time_study_templates')->insertGetId(['name' => 'Template '.$unique, 'purpose' => $purpose, 'created_at' => now(), 'updated_at' => now()]);
        $status = $o['status'] ?? 'published';
        $version = DB::table('time_study_template_versions')->insertGetId([
            'time_study_template_id' => $templateId,
            'version_no' => $status === 'published' ? 1 : null,
            'status' => $status,
            'definition' => json_encode(['rules' => $rules, 'fields' => $o['fields'] ?? [], 'counts_in_matrix' => false, 'approval' => $o['approval'] ?? 'always', 'allowance_pct' => 14]),
            'published_at' => $status === 'published' ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('factory', 'operation', 'employees', 'style', 'styleOperation', 'version') + ['style_operation' => $styleOperation];
    }
}
