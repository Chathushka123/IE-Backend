<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\TimeStudyPlanRepository as Plans;
use App\Http\Repositories\TimeStudyRepository;
use App\Http\Repositories\TimeStudyTemplateRepository as Templates;
use App\Support\TimeStudy\TemplateDefinition;
use App\TimeStudyPlanItem;
use App\User;
use Illuminate\Support\Facades\DB;

class TimeStudyPlanRepositoryTest extends RepositoryTestCase
{
    // ---------------------------------------------------------------
    // create
    // ---------------------------------------------------------------

    public function testCreateMakesADraftPinnedToTheTemplatesLatestVersionWithItsFirstPairs()
    {
        $this->actingAsTestUser();
        $c = $this->context();

        $plan = Plans::create($this->planInput($c, ['pairs' => [$this->pair($c, 0, 0), $this->pair($c, 1, 1)]]));

        $this->assertSame('draft', $plan['status']);
        $this->assertSame($c['versionId'], $plan['template']['version_id']);
        $this->assertCount(2, $plan['items']);
        $this->assertSame($c['employees'][1], $plan['items'][1]['employee']['id']);
        $this->assertSame(['pairs' => 2, 'pairs_done' => 0, 'skipped' => 0, 'studies_target' => 2, 'studies_done' => 0], $plan['progress']);
    }

    public function testCreateIgnoresADuplicatePair()
    {
        $this->actingAsTestUser();
        $c = $this->context();

        $plan = Plans::create($this->planInput($c, ['pairs' => [$this->pair($c, 0, 0), $this->pair($c, 0, 0)]]));

        $this->assertCount(1, $plan['items']);
    }

    /** @dataProvider badCreateInputs */
    public function testCreateRefusesWhatCannotBePlanned(string $change)
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $input = $this->planInput($c, ['pairs' => [$this->pair($c, 0, 0)]]);

        $input = match ($change) {
            'no name' => ['name' => ' '] + $input,
            'unknown factory' => ['factory_id' => 999999] + $input,
            'unpublished template' => ['template_id' => Templates::create(['name' => 'Unpublished '.uniqid(), 'purpose' => 'perf'])['id']] + $input,
            'operator from another factory' => ['pairs' => [['operation_id' => $c['operations'][0], 'employee_id' => $this->employee($this->factory(), 'X')]]] + $input,
            'unknown operation' => ['pairs' => [['operation_id' => 999999, 'employee_id' => $c['employees'][0]]]] + $input,
            'bad target' => ['target_per_pair' => 11] + $input,
            'bad study type' => ['time_study_type' => 'sometimes'] + $input,
        };

        $this->expectException(GeneralException::class);
        Plans::create($input);
    }

    public static function badCreateInputs(): array
    {
        return array_combine(
            ['no name', 'unknown factory', 'unpublished template', 'operator from another factory', 'unknown operation', 'bad target', 'bad study type'],
            array_map(fn ($c) => [$c], ['no name', 'unknown factory', 'unpublished template', 'operator from another factory', 'unknown operation', 'bad target', 'bad study type'])
        );
    }

    public function testCreateRefusesOperationsTheTemplateDoesNotCover()
    {
        $this->actingAsTestUser();
        $c = $this->context(['conditions' => []]);
        $scoped = $this->publishedTemplate('Only the first operation', [['field' => 'operation', 'ref_id' => $c['operations'][0]]]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('covered by the template');
        Plans::create($this->planInput($c, ['template_id' => $scoped['template_id'], 'pairs' => [$this->pair($c, 1, 0)]]));
    }

    // ---------------------------------------------------------------
    // layout rules at activation
    // ---------------------------------------------------------------

    public function testADraftMayBreakTheLayoutButSaysSoAndCannotStart()
    {
        $this->actingAsTestUser();
        $c = $this->context(['layout' => ['operators_per_operation' => ['mode' => 'one'], 'operations_per_operator' => 'one']]);
        $plan = Plans::create($this->planInput($c, ['time_study_type' => 'interview_training', 'pairs' => [$this->pair($c, 0, 0), $this->pair($c, 0, 1)]]));

        $this->assertStringContainsString('has 2 operators', $plan['problems'][0]);
        try {
            Plans::activate($plan['id']);
            $this->fail('A plan that breaks the layout must not start.');
        } catch (GeneralException $e) {
            $this->assertStringContainsString('cannot start yet', $e->getMessage());
            $this->assertStringContainsString('one operator per operation', $e->getMessage());
        }
        $this->assertSame('draft', Plans::find($plan['id'])['status']);
    }

    public function testSeveralOperatorsPerOperationNeedsTheMinimumBeforeStarting()
    {
        $this->actingAsTestUser();
        $c = $this->context(['layout' => ['operators_per_operation' => ['mode' => 'several', 'min' => 2], 'operations_per_operator' => 'one']]);
        $plan = Plans::create($this->planInput($c, ['time_study_type' => 'interview_training', 'pairs' => [$this->pair($c, 0, 0)]]));

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('needs at least 2');
        Plans::activate($plan['id']);
    }

    public function testAPlanThatMeetsTheLayoutStarts()
    {
        $this->actingAsTestUser();
        $c = $this->context(['layout' => ['operators_per_operation' => ['mode' => 'several', 'min' => 2], 'operations_per_operator' => 'one']]);
        $plan = Plans::create($this->planInput($c, ['time_study_type' => 'interview_training', 'pairs' => [$this->pair($c, 0, 0), $this->pair($c, 0, 1)]]));

        $started = Plans::activate($plan['id']);

        $this->assertSame('active', $started['status']);
        $this->assertSame([], $started['problems']);
    }

    public function testAPlanNeedsAStudyTypeAndAProductionFloorPlanNeedsAStyle()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = Plans::create($this->planInput($c, ['pairs' => [$this->pair($c, 0, 0)]]));

        $this->assertThrowsMessage('study type', fn () => Plans::activate($plan['id']));

        Plans::update($plan['id'], ['time_study_type' => 'production_floor']);
        $this->assertThrowsMessage('needs a style', fn () => Plans::activate($plan['id']));

        Plans::update($plan['id'], ['style_id' => $c['styleId']]);
        $this->assertSame('active', Plans::activate($plan['id'])['status']);
    }

    public function testAnEmptyPlanCannotStart()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = Plans::create($this->planInput($c, ['time_study_type' => 'interview_training']));

        $this->assertThrowsMessage('Add at least one operator', fn () => Plans::activate($plan['id']));
    }

    public function testASetupChangeIsRefusedOnceThePlanIsRunning()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);

        $this->assertThrowsMessage('before the plan starts', fn () => Plans::update($plan['id'], ['target_per_pair' => 3]));
        $this->assertSame('Renamed', Plans::update($plan['id'], ['name' => 'Renamed'])['name']);
    }

    // ---------------------------------------------------------------
    // changing a running plan
    // ---------------------------------------------------------------

    public function testARunningPlanRefusesAnAdditionThatBreaksALimitButAllowsOneThatDoesNot()
    {
        $this->actingAsTestUser();
        $c = $this->context(['layout' => ['operators_per_operation' => ['mode' => 'one'], 'operations_per_operator' => 'one']]);
        $plan = $this->runningPlan($c, [$this->pair($c, 0, 0)]);

        $this->assertThrowsMessage('breaks the template', fn () => Plans::addPairs($plan['id'], [$this->pair($c, 0, 1)]));
        $this->assertCount(1, Plans::find($plan['id'])['items'], 'the refused pair must not be left behind');

        $added = Plans::addPairs($plan['id'], [$this->pair($c, 1, 1)]);
        $this->assertCount(2, $added['items']);
    }

    public function testASkippedPairLeavesAShortfallThatDoesNotBlockTheRunningPlan()
    {
        $this->actingAsTestUser();
        $c = $this->context(['layout' => ['operators_per_operation' => ['mode' => 'several', 'min' => 2], 'operations_per_operator' => 'one']]);
        $plan = $this->runningPlan($c, [$this->pair($c, 0, 0), $this->pair($c, 0, 1)]);
        $itemId = $plan['items'][1]['id'];

        $skipped = Plans::skipPair($plan['id'], $itemId, 'Operator is absent');

        $this->assertSame('skipped', $skipped['items'][1]['status']);
        $this->assertSame(1, $skipped['progress']['skipped']);
        $this->assertSame([], $skipped['problems']);
        $this->assertSame(1, $skipped['progress']['pairs']);
    }

    public function testSkippingNeedsAReasonAndARunningPlan()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $draft = Plans::create($this->planInput($c, ['pairs' => [$this->pair($c, 0, 0)]]));
        $running = $this->runningPlan($c);

        $this->assertThrowsMessage('while a plan is running', fn () => Plans::skipPair($draft['id'], $draft['items'][0]['id'], 'x'));
        $this->assertThrowsMessage('Say why', fn () => Plans::skipPair($running['id'], $running['items'][0]['id'], '  '));
    }

    public function testASkippedPairCanComeBackUnlessItBreaksALimit()
    {
        $this->actingAsTestUser();
        $c = $this->context(['layout' => ['operators_per_operation' => ['mode' => 'one'], 'operations_per_operator' => 'several']]);
        $plan = $this->runningPlan($c, [$this->pair($c, 0, 0)]);
        $firstItem = $plan['items'][0]['id'];
        Plans::skipPair($plan['id'], $firstItem, 'Absent');
        Plans::addPairs($plan['id'], [$this->pair($c, 0, 1)]);

        $this->assertThrowsMessage('breaks the template', fn () => Plans::restorePair($plan['id'], $firstItem));
    }

    public function testAPairIsRemovedFromADraftButOnlySkippedOnceItHasAStudy()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $draft = Plans::create($this->planInput($c, ['pairs' => [$this->pair($c, 0, 0), $this->pair($c, 1, 1)]]));
        $this->assertCount(1, Plans::removePair($draft['id'], $draft['items'][0]['id'])['items']);

        $running = $this->runningPlan($c, [$this->pair($c, 0, 0)]);
        $this->record($c, $running['items'][0]['id'], 0, 0);

        $this->assertThrowsMessage('Skip it instead', fn () => Plans::removePair($running['id'], $running['items'][0]['id']));
    }

    // ---------------------------------------------------------------
    // claiming and recording
    // ---------------------------------------------------------------

    public function testClaimingAPairReservesItForThatAnalyst()
    {
        $me = $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);

        $claimed = Plans::claim($plan['id'], $plan['items'][0]['id']);

        $this->assertTrue($claimed['items'][0]['claimed_by_me']);
        $this->assertSame($me->id, $claimed['items'][0]['claimed_by']['id']);
        // The same analyst renewing is fine.
        $this->assertTrue(Plans::claim($plan['id'], $plan['items'][0]['id'])['items'][0]['claimed_by_me']);
    }

    public function testAnotherAnalystCannotTakeAPairThatIsHeldUntilTheClaimExpires()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);
        $itemId = $plan['items'][0]['id'];
        Plans::claim($plan['id'], $itemId);

        $this->actingAsTestUser();
        $this->assertThrowsMessage('already timing', fn () => Plans::claim($plan['id'], $itemId));
        $this->assertFalse(Plans::find($plan['id'])['items'][0]['claimed_by_me']);

        TimeStudyPlanItem::whereKey($itemId)->update(['claimed_until' => now()->subMinute()]);
        $this->assertTrue(Plans::claim($plan['id'], $itemId)['items'][0]['claimed_by_me']);
    }

    public function testOnlyTheHolderCanReleaseAPair()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);
        $itemId = $plan['items'][0]['id'];
        Plans::claim($plan['id'], $itemId);

        $this->actingAsTestUser();
        $this->assertNotNull(Plans::release($plan['id'], $itemId)['items'][0]['claimed_by']);
    }

    public function testASkippedPairAnInactivePlanAndADonePairCannotBeClaimed()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $draft = Plans::create($this->planInput($c, ['pairs' => [$this->pair($c, 0, 0)]]));
        $this->assertThrowsMessage('not running', fn () => Plans::claim($draft['id'], $draft['items'][0]['id']));

        $plan = $this->runningPlan($c, [$this->pair($c, 0, 0), $this->pair($c, 1, 1)]);
        Plans::skipPair($plan['id'], $plan['items'][1]['id'], 'Absent');
        $this->assertThrowsMessage('was skipped', fn () => Plans::claim($plan['id'], $plan['items'][1]['id']));

        $this->record($c, $plan['items'][0]['id'], 0, 0);
        $this->assertThrowsMessage('already recorded', fn () => Plans::claim($plan['id'], $plan['items'][0]['id']));
    }

    public function testASavedStudyIsLinkedCountedAndReleasesTheClaim()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c, [$this->pair($c, 0, 0), $this->pair($c, 1, 1)]);
        $itemId = $plan['items'][0]['id'];
        Plans::claim($plan['id'], $itemId);

        $study = $this->record($c, $itemId, 0, 0);

        $this->assertSame($itemId, (int) $study->time_study_plan_item_id);
        $after = Plans::find($plan['id']);
        $this->assertTrue($after['items'][0]['done']);
        $this->assertNull($after['items'][0]['claimed_by']);
        $this->assertSame(1, $after['progress']['pairs_done']);
        $this->assertSame(1, $after['progress']['studies_done']);
    }

    public function testAPairNeedsAsManyStudiesAsTheTarget()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c, [$this->pair($c, 0, 0)], ['target_per_pair' => 3]);
        $itemId = $plan['items'][0]['id'];

        $this->record($c, $itemId, 0, 0);
        $this->record($c, $itemId, 0, 0);
        $partial = Plans::find($plan['id']);
        $this->assertFalse($partial['items'][0]['done']);
        $this->assertSame(2, $partial['items'][0]['studies_done']);

        $this->record($c, $itemId, 0, 0);
        $this->assertTrue(Plans::find($plan['id'])['items'][0]['done']);
    }

    public function testARejectedStudyDoesNotCountSoThePairNeedsAnother()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c, [$this->pair($c, 0, 0)]);
        $study = $this->record($c, $plan['items'][0]['id'], 0, 0);

        DB::table('time_studies')->where('id', $study->id)->update(['status' => 'rejected']);

        $this->assertFalse(Plans::find($plan['id'])['items'][0]['done']);
    }

    /** @dataProvider mismatchedStudies */
    public function testAStudyMustMatchThePlannedPair(string $mismatch, string $message)
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c, [$this->pair($c, 0, 0)]);
        $itemId = $plan['items'][0]['id'];
        $input = $this->studyInput($c, $itemId, 0, 0);

        match ($mismatch) {
            'operator' => $input['employee_id'] = $c['employees'][1],
            'operation' => $input['operation_id'] = $c['operations'][1],
            'template' => $input['template_version_id'] = $this->publishedTemplate('Other '.uniqid())['version_id'],
            // A production-floor study needs a style to get as far as the plan check.
            'type' => [$input['time_study_type'] = 'production_floor', $input['style_id'] = $c['styleId']],
        };

        $this->assertThrowsMessage($message, fn () => TimeStudyRepository::createRec($input));
        $this->assertSame(0, DB::table('time_studies')->where('time_study_plan_item_id', $itemId)->count());
    }

    public static function mismatchedStudies(): array
    {
        return [
            'another operator' => ['operator', 'not for the operator and operation'],
            'another operation' => ['operation', 'not for the operator and operation'],
            'another template version' => ['template', 'different template version'],
            'another kind of study' => ['type', 'interview and training'],
        ];
    }

    public function testAStudyIsRefusedWhileAnotherAnalystHoldsThePair()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);
        $itemId = $plan['items'][0]['id'];
        Plans::claim($plan['id'], $itemId);

        $this->actingAsTestUser();

        $this->assertThrowsMessage('timing this pair right now', fn () => TimeStudyRepository::createRec($this->studyInput($c, $itemId, 0, 0)));
    }

    public function testNoStudyCanBeAddedToAPlanThatIsNotRunning()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);
        Plans::complete($plan['id']);

        $this->assertThrowsMessage('not running', fn () => TimeStudyRepository::createRec($this->studyInput($c, $plan['items'][0]['id'], 0, 0)));
    }

    // ---------------------------------------------------------------
    // finishing and listing
    // ---------------------------------------------------------------

    public function testAFinishedOrCancelledPlanCannotBeChanged()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $done = $this->runningPlan($c);
        Plans::complete($done['id']);
        $cancelled = Plans::create($this->planInput($c));
        Plans::cancel($cancelled['id']);

        $this->assertThrowsMessage('already completed', fn () => Plans::addPairs($done['id'], [$this->pair($c, 1, 1)]));
        $this->assertThrowsMessage('already cancelled', fn () => Plans::update($cancelled['id'], ['name' => 'x']));
        $this->assertThrowsMessage('Only a running plan', fn () => Plans::complete($cancelled['id']));
    }

    public function testListShowsOpenPlansWithProgressAndFiltersByStatusAndOwner()
    {
        $me = $this->actingAsTestUser();
        $c = $this->context();
        $running = $this->runningPlan($c, [$this->pair($c, 0, 0), $this->pair($c, 1, 1)]);
        $this->record($c, $running['items'][0]['id'], 0, 0);
        $draft = Plans::create($this->planInput($c, ['name' => 'My draft']));
        Plans::cancel(Plans::create($this->planInput($c, ['name' => 'Gone']))['id']);
        $this->actingAsTestUser();
        $theirs = Plans::create($this->planInput($c, ['name' => 'Their draft']));

        $open = collect(Plans::search(['factory_id' => $c['factory']]));

        $this->assertEqualsCanonicalizing([$running['id'], $draft['id'], $theirs['id']], $open->pluck('id')->all());
        $this->assertSame(1, $open->firstWhere('id', $running['id'])['progress']['pairs_done']);
        $this->assertSame(['Gone'], collect(Plans::search(['status' => 'cancelled']))->pluck('name')->all());
        $this->assertSame([$theirs['id']], collect(Plans::search(['mine' => 1, 'factory_id' => $c['factory']]))->pluck('id')->all());
        $this->assertNotSame($me->id, null);
    }

    public function testThePlanKeepsItsTemplateVersionWhenTheTemplateIsEditedLater()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = Plans::create($this->planInput($c));

        Templates::saveDraft($c['templateId'], ['definition' => ['approval' => 'always']]);
        Templates::publish($c['templateId']);

        $this->assertSame($c['versionId'], Plans::find($plan['id'])['template']['version_id']);
    }

    // ---------------------------------------------------------------
    // who may change a plan
    // ---------------------------------------------------------------

    public function testOnlyTheCreatorOrAnAdministratorCanShapeAPlan()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $draft = Plans::create($this->planInput($c, ['time_study_type' => 'interview_training', 'pairs' => [$this->pair($c, 0, 0)]]));
        $running = $this->runningPlan($c, [$this->pair($c, 0, 0), $this->pair($c, 1, 1)]);

        $this->actingAsTestUser();

        $this->assertFalse(Plans::find($draft['id'])['can_manage']);
        foreach ([
            fn () => Plans::update($draft['id'], ['name' => 'Mine now']),
            fn () => Plans::activate($draft['id']),
            fn () => Plans::addPairs($draft['id'], [$this->pair($c, 1, 1)]),
            fn () => Plans::removePair($draft['id'], $draft['items'][0]['id']),
            fn () => Plans::addPairs($running['id'], [$this->pair($c, 1, 2)]),
            fn () => Plans::restorePair($running['id'], $running['items'][0]['id']),
            fn () => Plans::complete($running['id']),
            fn () => Plans::cancel($running['id']),
        ] as $change) {
            $this->assertThrowsMessage('Only the person who made this plan', $change);
        }
    }

    public function testAnyAnalystCanDoTheSharedWorkOnSomeoneElsesRunningPlan()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c, [$this->pair($c, 0, 0), $this->pair($c, 1, 1)]);

        $this->actingAsTestUser();

        $claimed = Plans::claim($plan['id'], $plan['items'][0]['id']);
        $this->assertTrue($claimed['items'][0]['claimed_by_me']);
        $this->record($c, $plan['items'][0]['id'], 0, 0);
        $skipped = Plans::skipPair($plan['id'], $plan['items'][1]['id'], 'Operator is absent');
        $this->assertSame('skipped', $skipped['items'][1]['status']);
        $this->assertFalse($skipped['can_manage']);
    }

    public function testTheCreatorCanShapeTheirOwnPlan()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);

        $this->assertTrue(Plans::find($plan['id'])['can_manage']);
        $this->assertSame('completed', Plans::complete($plan['id'])['status']);
    }

    public function testAnAdministratorCanShapeAnyPlan()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);

        $admin = $this->actingAsTestUser();
        $roleId = DB::table('roles')->where('role_code', 'Admin')->value('id')
            ?? DB::table('roles')->insertGetId(['role_code' => 'Admin', 'description' => 'Admin', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->where('id', $admin->id)->update(['role_id' => $roleId]);
        $admin->role_id = $roleId;

        $this->assertTrue(Plans::find($plan['id'])['can_manage']);
        $this->assertSame('cancelled', Plans::cancel($plan['id'])['status']);
    }

    public function testTheSystemAdministratorCanShapeAnyPlan()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);

        $id = DB::table('users')->where('email', 'sysadmin@gmail.com')->value('id')
            ?? DB::table('users')->insertGetId(['name' => 'sysadmin', 'email' => 'sysadmin@gmail.com', 'password' => bcrypt('Password1'), 'is_active' => true, 'common_user_state' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs(User::find($id));

        $this->assertTrue(Plans::find($plan['id'])['can_manage']);
    }

    public function testThePlanSaysWhoMadeIt()
    {
        $me = $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);

        $this->assertSame($me->name, Plans::find($plan['id'])['created_by']);
        $this->assertSame($me->name, collect(Plans::search(['factory_id' => $c['factory']]))->firstWhere('id', $plan['id'])['created_by']);
    }

    // ---------------------------------------------------------------
    // claims lapse, and a pair cannot be over-recorded
    // ---------------------------------------------------------------

    public function testAClaimLastsAFewMinutesSoItLapsesWhenNobodyRenewsIt()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);
        $itemId = $plan['items'][0]['id'];

        Plans::claim($plan['id'], $itemId);

        $until = TimeStudyPlanItem::find($itemId)->claimed_until;
        $this->assertTrue($until->between(now()->addMinutes(Plans::CLAIM_MINUTES - 1), now()->addMinutes(Plans::CLAIM_MINUTES + 1)));
        $this->assertLessThanOrEqual(10, Plans::CLAIM_MINUTES);
    }

    public function testRenewingAClaimExtendsIt()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);
        $itemId = $plan['items'][0]['id'];
        Plans::claim($plan['id'], $itemId);
        TimeStudyPlanItem::whereKey($itemId)->update(['claimed_until' => now()->addSeconds(30)]);

        Plans::claim($plan['id'], $itemId);

        $this->assertTrue(TimeStudyPlanItem::find($itemId)->claimed_until->greaterThan(now()->addMinutes(Plans::CLAIM_MINUTES - 1)));
    }

    public function testNoMoreStudiesAreAcceptedOnceAPairHasItsTarget()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c, [$this->pair($c, 0, 0)], ['target_per_pair' => 2]);
        $itemId = $plan['items'][0]['id'];
        $this->record($c, $itemId, 0, 0);
        $this->record($c, $itemId, 0, 0);

        $this->assertThrowsMessage('already recorded', fn () => $this->record($c, $itemId, 0, 0));
        $this->assertSame(2, DB::table('time_studies')->where('time_study_plan_item_id', $itemId)->count());
    }

    public function testARejectedStudyMakesRoomForAnother()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);
        $itemId = $plan['items'][0]['id'];
        $first = $this->record($c, $itemId, 0, 0);
        DB::table('time_studies')->where('id', $first->id)->update(['status' => 'rejected']);

        $this->record($c, $itemId, 0, 0);

        $this->assertSame(2, DB::table('time_studies')->where('time_study_plan_item_id', $itemId)->count());
    }

    // ---------------------------------------------------------------
    // history
    // ---------------------------------------------------------------

    public function testFinishedAndCancelledPlansCanBeListedWithWhenTheyEnded()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $finished = $this->runningPlan($c);
        Plans::complete($finished['id']);
        $cancelled = Plans::create($this->planInput($c));
        Plans::cancel($cancelled['id']);
        $open = Plans::create($this->planInput($c));

        $history = collect(Plans::search(['factory_id' => $c['factory'], 'status' => ['completed', 'cancelled']]));

        $this->assertEqualsCanonicalizing([$finished['id'], $cancelled['id']], $history->pluck('id')->all());
        $this->assertNotNull($history->firstWhere('id', $finished['id'])['completed_at']);
        $this->assertNull(collect(Plans::search(['factory_id' => $c['factory']]))->firstWhere('id', $open['id'])['completed_at']);
    }

    public function testAFinishedPlanCanStillBeReadWithItsPairsAndProgress()
    {
        $this->actingAsTestUser();
        $c = $this->context();
        $plan = $this->runningPlan($c);
        $this->record($c, $plan['items'][0]['id'], 0, 0);
        Plans::complete($plan['id']);

        $read = Plans::find($plan['id']);

        $this->assertSame('completed', $read['status']);
        $this->assertTrue($read['items'][0]['done']);
        $this->assertSame([], $read['problems']);
    }

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    private function assertThrowsMessage(string $fragment, callable $action): void
    {
        try {
            $action();
        } catch (GeneralException $e) {
            $this->assertStringContainsString($fragment, $e->getMessage());

            return;
        }
        $this->fail("Expected a GeneralException mentioning '{$fragment}'.");
    }

    private function planInput(array $c, array $extra = []): array
    {
        return $extra + ['name' => 'Plan '.uniqid(), 'factory_id' => $c['factory'], 'template_id' => $c['templateId'], 'pairs' => []];
    }

    private function pair(array $c, int $operationIndex, int $employeeIndex): array
    {
        return ['operation_id' => $c['operations'][$operationIndex], 'employee_id' => $c['employees'][$employeeIndex]];
    }

    /** A started plan (interview and training) with the given pairs, or one pair by default. */
    private function runningPlan(array $c, ?array $pairs = null, array $extra = []): array
    {
        $plan = Plans::create($this->planInput($c, $extra + ['time_study_type' => 'interview_training', 'pairs' => $pairs ?? [$this->pair($c, 0, 0)]]));

        return Plans::activate($plan['id']);
    }

    private function studyInput(array $c, int $itemId, int $operationIndex, int $employeeIndex): array
    {
        $laps = [];
        for ($n = 1; $n <= 8; $n++) {
            $laps[] = ['lap_no' => $n, 'duration_ms' => 30000 + $n * 100, 'down_time_ms' => 0, 'net_ms' => 30000 + $n * 100, 'is_excluded' => false];
        }

        return [
            'factory_id' => $c['factory'],
            'study_date' => now()->toDateString(),
            'time_study_type' => 'interview_training',
            'operation_id' => $c['operations'][$operationIndex],
            'employee_id' => $c['employees'][$employeeIndex],
            'template_version_id' => $c['versionId'],
            'time_study_plan_item_id' => $itemId,
            'total_down_time_ms' => 0,
            'total_hold_ms' => 0,
            'total_cycle_ms' => array_sum(array_column($laps, 'duration_ms')),
            'laps' => $laps,
        ];
    }

    private function record(array $c, int $itemId, int $operationIndex, int $employeeIndex)
    {
        return TimeStudyRepository::createRec($this->studyInput($c, $itemId, $operationIndex, $employeeIndex));
    }

    private function publishedTemplate(string $name, array $conditions = []): array
    {
        $created = Templates::create(['name' => $name, 'purpose' => 'perf', 'conditions' => $conditions]);
        Templates::publish($created['id']);

        return ['template_id' => $created['id'], 'version_id' => Templates::startFrom($created['id'])['version_id']];
    }

    /**
     * A factory with two operations, three operators, a style whose bulletin has both operations, and a
     * published operator-performance template. Options: layout (merged into the definition).
     */
    private function context(array $o = []): array
    {
        $factory = $this->factory();
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $baseCategoryId = DB::table('base_operation_categories')->insertGetId(['name' => 'Cat '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $gradeId = DB::table('operation_grades')->insertGetId(['name' => 'Grade '.$unique, 'code' => 'OG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $groupId = DB::table('product_groups')->insertGetId(['name' => 'Group '.$unique, 'code' => 'PG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Category '.$unique, 'code' => 'PC'.$unique, 'product_group_id' => $groupId, 'created_at' => now(), 'updated_at' => now()]);
        $machineCategoryId = DB::table('machine_categories')->insertGetId(['name' => 'MC '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $machineId = DB::table('machine_types')->insertGetId(['name' => 'Machine '.$unique, 'code' => 'MT'.$unique, 'machine_category_id' => $machineCategoryId, 'created_at' => now(), 'updated_at' => now()]);

        $operations = [];
        foreach ([1, 2] as $i) {
            $baseOperationId = DB::table('base_operations')->insertGetId(['name' => "Op {$i} {$unique}", 'code' => "BO{$i}{$unique}", 'base_operation_category_id' => $baseCategoryId, 'created_at' => now(), 'updated_at' => now()]);
            $operations[] = DB::table('operations')->insertGetId([
                'code' => "OP{$i}{$unique}", 'description' => "Operation {$i} {$unique}", 'base_operation_id' => $baseOperationId, 'grade_id' => $gradeId,
                'product_category_id' => $categoryId, 'machine_type_id' => $machineId, 'smv' => 0.5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $employees = array_map(fn ($name) => $this->employee($factory, $name), ['Jane', 'Joan', 'June']);

        $customerId = DB::table('customers')->insertGetId(['description' => 'Customer '.$unique, 'code' => 'CU'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $seasonId = DB::table('seasons')->insertGetId(['name' => 'Season '.$unique, 'code' => 'SE'.$unique, 'customer_id' => $customerId, 'created_at' => now(), 'updated_at' => now()]);
        $styleId = DB::table('styles')->insertGetId(['name' => 'Style '.$unique, 'style_code' => 'ST'.$unique, 'product_category_id' => $categoryId, 'customer_id' => $customerId, 'season_id' => $seasonId, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($operations as $i => $operationId) {
            DB::table('style_operations')->insert(['style_id' => $styleId, 'operation_id' => $operationId, 'sequence_no' => $i + 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        $definition = TemplateDefinition::normalize(['layout' => $o['layout'] ?? []], 'perf');
        $created = Templates::create(['name' => 'Plan template '.$unique, 'purpose' => 'perf', 'definition' => $definition]);
        Templates::publish($created['id']);

        return [
            'factory' => $factory, 'operations' => $operations, 'employees' => $employees, 'styleId' => $styleId,
            'templateId' => $created['id'], 'versionId' => Templates::startFrom($created['id'])['version_id'],
        ];
    }

    private function factory(): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));

        return DB::table('factories')->insertGetId(['name' => 'Plant '.$unique, 'code' => 'PL'.substr($unique, 0, 6), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function employee(int $factoryId, string $first): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $hierarchyId = DB::table('management_hierarchies')->insertGetId(['name' => 'Operator '.$unique, 'seq_no' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('employees')->insertGetId([
            'employee_no' => 'EMP-'.$unique, 'identification_no' => 'NIC-'.$unique, 'first_name' => $first, 'last_name' => 'Doe',
            'management_hierarchy_id' => $hierarchyId, 'factory_id' => $factoryId, 'employee_status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
