<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\TimeStudyTemplateRepository as Repo;
use App\Support\FactoryContext;
use App\TimeStudyTemplate;
use App\TimeStudyTemplateVersion;
use Illuminate\Support\Facades\DB;

class TimeStudyTemplateRepositoryTest extends RepositoryTestCase
{
    // ---------------------------------------------------------------
    // create / save draft
    // ---------------------------------------------------------------

    public function testCreateMakesAnUnpublishedDraftFromThePreset()
    {
        $this->actingAsTestUser();

        $template = Repo::create(['name' => 'Collars', 'purpose' => 'perf', 'preset' => 'strict']);

        $this->assertNull($template['published']);
        $this->assertSame('draft', $template['draft']['status']);
        $this->assertSame(12, $template['draft']['definition']['rules']['min_laps']['value']);
        $this->assertTrue($template['editable']);
    }

    public function testCreateRejectsADuplicateOrMissingNameAndAnUnknownPurpose()
    {
        $this->actingAsTestUser();
        Repo::create(['name' => 'Collars', 'purpose' => 'perf']);

        foreach ([['name' => 'Collars', 'purpose' => 'perf'], ['name' => '', 'purpose' => 'perf'], ['name' => 'X', 'purpose' => 'other']] as $bad) {
            try {
                Repo::create($bad);
                $this->fail('Expected a validation error for '.json_encode($bad));
            } catch (GeneralException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testCreateStoresConditionsAndLabelsThemAndIgnoresDuplicates()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();

        $template = Repo::create(['name' => 'Lockstitch', 'purpose' => 'perf', 'conditions' => [
            ['field' => 'machine_type', 'ref_id' => $m['machine']],
            ['field' => 'machine_type', 'ref_id' => $m['machine']],
        ]]);

        $this->assertCount(1, $template['draft']['conditions']);
        $this->assertSame('Machine '.$m['unique'], $template['draft']['conditions'][0]['label']);
    }

    public function testConditionsMustNameAKnownFieldAndAnExistingRow()
    {
        $this->actingAsTestUser();

        foreach ([
            [['field' => 'colour', 'ref_id' => 1]],
            [['field' => 'machine_type', 'ref_id' => 999999]],
            [['field' => 'machine_type', 'ref_id' => null]],
            [['field' => 'machine_type']],
        ] as $conditions) {
            try {
                Repo::create(['name' => 'T'.uniqid(), 'purpose' => 'perf', 'conditions' => $conditions]);
                $this->fail('Expected the condition to be refused: '.json_encode($conditions));
            } catch (GeneralException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertSame(0, TimeStudyTemplate::whereNull('system_key')->count());
    }

    public function testSaveDraftNormalisesTheDefinitionAndKeysCustomFields()
    {
        $this->actingAsTestUser();
        $id = Repo::create(['name' => 'T', 'purpose' => 'perf'])['id'];

        $saved = Repo::saveDraft($id, ['definition' => ['fields' => [['label' => 'Fabric', 'required' => true]], 'rules' => ['min_laps' => ['value' => '9']]]]);

        $this->assertSame('fabric', $saved['draft']['definition']['fields'][0]['key']);
        $this->assertSame(9, $saved['draft']['definition']['rules']['min_laps']['value']);
    }

    public function testApplyingAPresetReplacesRulesButKeepsQuestionsAndTheManualSwitch()
    {
        $this->actingAsTestUser();
        $id = Repo::create(['name' => 'T', 'purpose' => 'perf', 'manual_only' => true])['id'];
        Repo::saveDraft($id, ['definition' => ['manual_only' => true, 'fields' => [['label' => 'Fabric']]]]);

        $draft = Repo::saveDraft($id, ['preset' => 'strict'])['draft']['definition'];

        $this->assertSame(12, $draft['rules']['min_laps']['value']);
        $this->assertSame('always', $draft['approval']);
        $this->assertSame(['fabric'], array_column($draft['fields'], 'key'));
        $this->assertTrue($draft['manual_only']);
    }

    public function testSaveDraftReplacesTheConditions()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $id = Repo::create(['name' => 'T', 'purpose' => 'perf', 'conditions' => [['field' => 'machine_type', 'ref_id' => $m['machine']]]])['id'];

        $saved = Repo::saveDraft($id, ['conditions' => [['field' => 'product_group', 'ref_id' => $m['group']]]]);

        $this->assertSame(['product_group'], array_column($saved['draft']['conditions'], 'field'));
        $this->assertSame(1, DB::table('time_study_template_conditions')->count());
    }

    public function testSaveDraftKeepsTheConditionsWhenNoneAreSent()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $id = Repo::create(['name' => 'T', 'purpose' => 'perf', 'conditions' => [['field' => 'machine_type', 'ref_id' => $m['machine']]]])['id'];

        $saved = Repo::saveDraft($id, ['name' => 'Renamed']);

        $this->assertSame('Renamed', $saved['name']);
        $this->assertCount(1, $saved['draft']['conditions']);
    }

    public function testSaveDraftRefusesAnotherTemplatesNameButAllowsItsOwn()
    {
        $this->actingAsTestUser();
        Repo::create(['name' => 'Taken', 'purpose' => 'perf']);
        $id = Repo::create(['name' => 'Mine', 'purpose' => 'perf'])['id'];

        $this->assertSame('Mine', Repo::saveDraft($id, ['name' => 'Mine'])['name']);
        $this->expectException(GeneralException::class);
        Repo::saveDraft($id, ['name' => 'Taken']);
    }

    public function testThePurposeCanChangeUntilTheFirstPublishThenIsFixed()
    {
        $this->actingAsTestUser();
        $id = Repo::create(['name' => 'T', 'purpose' => 'perf'])['id'];

        $switched = Repo::saveDraft($id, ['purpose' => 'smv']);
        $this->assertSame('smv', $switched['purpose']);
        $this->assertFalse($switched['draft']['definition']['counts_in_matrix']);

        Repo::publish($id);
        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('cannot change once');
        Repo::saveDraft($id, ['purpose' => 'perf']);
    }

    // ---------------------------------------------------------------
    // publish / versions
    // ---------------------------------------------------------------

    public function testPublishFreezesTheDraftAsVersionOne()
    {
        $user = $this->actingAsTestUser();
        $id = Repo::create(['name' => 'T', 'purpose' => 'perf'])['id'];

        $template = Repo::publish($id);

        $this->assertSame(1, $template['published']['version_no']);
        $this->assertNull($template['draft']);
        $this->assertSame($user->id, TimeStudyTemplateVersion::where('time_study_template_id', $id)->first()->published_by_id);
    }

    public function testPublishRefusesADefinitionThatBreaksTheMatrixFloor()
    {
        $this->actingAsTestUser();
        $id = Repo::create(['name' => 'T', 'purpose' => 'perf'])['id'];
        Repo::saveDraft($id, ['definition' => ['counts_in_matrix' => true, 'rules' => ['min_laps' => ['value' => 2, 'severity' => 'warn']]]]);

        try {
            Repo::publish($id);
            $this->fail('Expected publishing to be refused.');
        } catch (GeneralException $e) {
            $this->assertStringContainsString('at least 3', $e->getMessage());
        }
        $this->assertSame(0, TimeStudyTemplateVersion::where('status', 'published')->where('time_study_template_id', $id)->count());
    }

    public function testPublishWithoutADraftIsRefused()
    {
        $this->actingAsTestUser();
        $id = $this->publishedTemplate('T');

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('no changes to publish');

        Repo::publish($id);
    }

    public function testEditingAPublishedTemplateCreatesADraftAndPublishingItMakesVersionTwo()
    {
        $this->actingAsTestUser();
        $id = $this->publishedTemplate('T');

        $edited = Repo::saveDraft($id, ['definition' => ['rules' => ['min_laps' => ['value' => 15, 'severity' => 'block']]]]);
        $this->assertSame(1, $edited['published']['version_no']);
        $this->assertSame(15, $edited['draft']['definition']['rules']['min_laps']['value']);

        $published = Repo::publish($id);
        $this->assertSame(2, $published['published']['version_no']);
        $this->assertSame([2, 1], array_column($published['versions'], 'version_no'));
    }

    public function testAPublishedVersionKeepsItsRulesWhenTheTemplateIsEditedLater()
    {
        $this->actingAsTestUser();
        $id = $this->publishedTemplate('T');
        $before = TimeStudyTemplateVersion::where('time_study_template_id', $id)->first()->definition;

        Repo::saveDraft($id, ['definition' => ['rules' => ['min_laps' => ['value' => 20, 'severity' => 'block']]]]);
        Repo::publish($id);

        $this->assertEquals($before, TimeStudyTemplateVersion::where('time_study_template_id', $id)->where('version_no', 1)->first()->definition);
    }

    public function testManualOnlyReachesTheLiveTemplateOnlyWhenPublished()
    {
        $this->actingAsTestUser();
        $id = $this->publishedTemplate('T');

        Repo::saveDraft($id, ['manual_only' => true]);
        $this->assertFalse((bool) TimeStudyTemplate::find($id)->manual_only);
        $this->assertTrue(Repo::find($id)['draft']['definition']['manual_only']);

        Repo::publish($id);
        $this->assertTrue((bool) TimeStudyTemplate::find($id)->manual_only);
    }

    public function testDiscardingADraftGoesBackToThePublishedVersion()
    {
        $this->actingAsTestUser();
        $id = $this->publishedTemplate('T');
        Repo::saveDraft($id, ['definition' => ['approval' => 'always']]);

        $after = Repo::discardDraft($id);

        $this->assertNull($after['draft']);
        $this->assertSame(1, $after['published']['version_no']);
        $this->assertSame(0, DB::table('time_study_template_versions')->where('status', 'draft')->count());
    }

    public function testADraftOfANeverPublishedTemplateCannotBeDiscarded()
    {
        $this->actingAsTestUser();
        $id = Repo::create(['name' => 'T', 'purpose' => 'perf'])['id'];

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('never been published');

        Repo::discardDraft($id);
    }

    // ---------------------------------------------------------------
    // clone / archive / system template
    // ---------------------------------------------------------------

    public function testCloneCopiesRulesAndScopeIntoANewDraftWithAUniqueName()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $id = $this->publishedTemplate('Collars', [['field' => 'product_group', 'ref_id' => $m['group']]]);

        $first = Repo::cloneTemplate($id);
        $second = Repo::cloneTemplate($id);

        $this->assertSame('Collars (copy)', $first['name']);
        $this->assertSame('Collars (copy 2)', $second['name']);
        $this->assertNull($first['published']);
        $this->assertSame(['product_group'], array_column($first['draft']['conditions'], 'field'));
    }

    public function testArchivingHidesATemplateFromTheDefaultList()
    {
        $this->actingAsTestUser();
        $id = $this->publishedTemplate('Old');

        Repo::archive($id);

        $this->assertNotContains('Old', array_column(Repo::search(), 'name'));
        $this->assertContains('Old', array_column(Repo::search(['include_archived' => 1]), 'name'));
    }

    public function testAnArchivedTemplateCannotBeEditedOrPublished()
    {
        $this->actingAsTestUser();
        $id = $this->publishedTemplate('Old');
        Repo::archive($id);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('archived');

        Repo::saveDraft($id, ['name' => 'New name']);
    }

    public function testTheLegacySystemTemplateCannotBeEditedOrArchivedButCanBeCopied()
    {
        $this->actingAsTestUser();
        $legacyId = TimeStudyTemplate::where('system_key', 'legacy')->value('id');

        foreach ([fn () => Repo::saveDraft($legacyId, ['name' => 'Hacked']), fn () => Repo::archive($legacyId), fn () => Repo::publish($legacyId)] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the system template to be protected.');
            } catch (GeneralException $e) {
                $this->assertStringContainsString('used by the system', $e->getMessage());
            }
        }

        $copy = Repo::cloneTemplate($legacyId);
        $this->assertNull($copy['system_key']);
        $this->assertTrue($copy['editable']);
    }

    // ---------------------------------------------------------------
    // list
    // ---------------------------------------------------------------

    public function testListShowsStatusVersionScopeAndStudyCount()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $id = $this->publishedTemplate('Collars', [['field' => 'machine_type', 'ref_id' => $m['machine']]]);
        Repo::create(['name' => 'Unfinished', 'purpose' => 'smv']);
        $versionId = TimeStudyTemplateVersion::where('time_study_template_id', $id)->value('id');
        $this->insertStudy($m['operation'], $versionId);
        $this->insertStudy($m['operation'], $versionId);

        $rows = collect(Repo::search())->keyBy('name');

        $this->assertSame('published', $rows['Collars']['status']);
        $this->assertSame(1, $rows['Collars']['published_version_no']);
        $this->assertSame(2, $rows['Collars']['studies_count']);
        $this->assertSame('Machine '.$m['unique'], $rows['Collars']['scope'][0]['label']);
        $this->assertSame('draft', $rows['Unfinished']['status']);
        $this->assertSame('smv', $rows['Unfinished']['purpose']);
        $this->assertTrue($rows['Collars']['counts_in_matrix']);
    }

    public function testListFiltersByPurposeAndNameAndFlagsAPendingDraft()
    {
        $this->actingAsTestUser();
        $id = $this->publishedTemplate('Alpha');
        $this->publishedTemplate('Beta', [], 'smv');
        Repo::saveDraft($id, ['definition' => ['approval' => 'always']]);

        $this->assertSame(['Beta'], array_column(Repo::search(['purpose' => 'smv']), 'name'));
        $this->assertSame(['Alpha'], array_column(Repo::search(['q' => 'lph']), 'name'));
        $this->assertTrue(collect(Repo::search(['q' => 'Alpha']))->first()['has_draft']);
    }

    public function testStudyCountsOnlyIncludeStudiesFromTheUsersFactories()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $id = $this->publishedTemplate('Collars');
        $versionId = TimeStudyTemplateVersion::where('time_study_template_id', $id)->value('id');
        $mine = $this->insertStudy($m['operation'], $versionId);
        $this->insertStudy($m['operation'], $versionId, $this->makeFactory());
        FactoryContext::set([DB::table('time_studies')->where('id', $mine)->value('factory_id')]);

        $this->assertSame(1, collect(Repo::search())->firstWhere('name', 'Collars')['studies_count']);
    }

    // ---------------------------------------------------------------
    // preview
    // ---------------------------------------------------------------

    public function testPreviewCountsTheOperationsADraftWouldCover()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $other = $this->makeOperation($m['baseOperation'], $m['category'], $this->makeMachineType());

        $preview = Repo::preview(['name' => 'Lockstitch', 'purpose' => 'perf', 'conditions' => [['field' => 'machine_type', 'ref_id' => $m['machine']]]]);

        $this->assertSame(1, $preview['scope']['matched_count']);
        $this->assertSame(2, $preview['scope']['total_operations']);
        $this->assertSame($m['operation'], $preview['scope']['matched_operations'][0]['id']);
        $this->assertNotContains($other, array_column($preview['scope']['matched_operations'], 'id'));
        $this->assertSame([], $preview['issues']);
    }

    public function testPreviewOfAManualOnlyTemplateMatchesNothingAutomatically()
    {
        $this->actingAsTestUser();
        $this->makeMasters();

        $preview = Repo::preview(['name' => 'Trial', 'purpose' => 'perf', 'manual_only' => true]);

        $this->assertSame(0, $preview['scope']['matched_count']);
        $this->assertTrue($preview['scope']['manual_only']);
    }

    public function testPreviewLeavesOutAnIncompleteConditionAndFlagsIt()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();

        $preview = Repo::preview(['name' => 'T', 'purpose' => 'perf', 'conditions' => [
            ['field' => 'machine_type', 'ref_id' => $m['machine']],
            ['field' => 'product_group', 'ref_id' => null],
        ]]);

        $this->assertSame(1, $preview['scope']['matched_count']);
        $this->assertContains('Choose a value for every condition, or remove it.', $preview['issues']);
    }

    public function testPreviewReportsWhereAMoreSpecificTemplateWins()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $second = $this->makeOperation($this->makeBaseOperation(), $m['category'], $m['machine']);
        $this->publishedTemplate('Pocket only', [['field' => 'operation', 'ref_id' => $m['operation']]]);

        $preview = Repo::preview(['name' => 'Lockstitch', 'purpose' => 'perf', 'conditions' => [['field' => 'machine_type', 'ref_id' => $m['machine']]]]);

        $this->assertSame(2, $preview['scope']['matched_count']);
        $this->assertSame(1, $preview['scope']['wins']);
        $this->assertSame('Pocket only', $preview['scope']['losses'][0]['name']);
        $this->assertSame(1, $preview['scope']['losses'][0]['count']);
        $this->assertNotSame($second, $m['operation']);
    }

    public function testPreviewTreatsATieAsAWinForTheNewerDraftAndIgnoresItself()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $id = $this->publishedTemplate('Same scope', [['field' => 'machine_type', 'ref_id' => $m['machine']]]);

        $newDraft = Repo::preview(['name' => 'Another', 'purpose' => 'perf', 'conditions' => [['field' => 'machine_type', 'ref_id' => $m['machine']]]]);
        $editingSelf = Repo::preview(['template_id' => $id, 'name' => 'Same scope', 'purpose' => 'perf', 'conditions' => [['field' => 'machine_type', 'ref_id' => $m['machine']]]]);

        $this->assertSame(1, $newDraft['scope']['wins']);
        $this->assertSame([], $newDraft['scope']['losses']);
        $this->assertSame([], $editingSelf['issues']);
    }

    public function testPreviewOnlyComparesTemplatesOfTheSamePurposeAndIgnoresManualOnesAndArchived()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $scope = [['field' => 'operation', 'ref_id' => $m['operation']]];
        $this->publishedTemplate('Other purpose', $scope, 'smv');
        $manual = $this->publishedTemplate('Manual', $scope);
        Repo::saveDraft($manual, ['manual_only' => true]);
        Repo::publish($manual);
        Repo::archive($this->publishedTemplate('Archived', $scope));

        $preview = Repo::preview(['name' => 'Broad', 'purpose' => 'perf', 'conditions' => [['field' => 'machine_type', 'ref_id' => $m['machine']]]]);

        $this->assertSame([], $preview['scope']['losses']);
    }

    public function testPreviewReportsProblemsInPlainLanguage()
    {
        $this->actingAsTestUser();
        $this->publishedTemplate('Taken');

        $preview = Repo::preview(['name' => 'Taken', 'purpose' => 'perf', 'definition' => ['counts_in_matrix' => true, 'rules' => ['min_laps' => ['value' => 1, 'severity' => 'warn']]]]);
        $blank = Repo::preview(['name' => ' ', 'purpose' => 'perf']);

        $this->assertContains('Another template already uses this name.', $preview['issues']);
        $this->assertNotEmpty(array_filter($preview['issues'], fn ($i) => str_contains($i, 'at least 3')));
        $this->assertContains('Give the template a name.', $blank['issues']);
    }

    // ---------------------------------------------------------------
    // resolve
    // ---------------------------------------------------------------

    public function testResolvePicksTheMostSpecificMatchingTemplate()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $this->publishedTemplate('Everything');
        $this->publishedTemplate('Lockstitch', [['field' => 'machine_type', 'ref_id' => $m['machine']]]);
        $this->publishedTemplate('This operation', [['field' => 'operation', 'ref_id' => $m['operation']]]);

        $resolved = Repo::resolve($m['operation'], 'perf');

        $this->assertSame('This operation', $resolved['chosen']['name']);
        $this->assertSame(3, $resolved['chosen']['candidate_count']);
        $this->assertSame(['operation'], array_column($resolved['chosen']['matched_conditions'], 'field'));
        $this->assertEqualsCanonicalizing(['Everything', 'Lockstitch'], array_column($resolved['alternatives'], 'name'));
    }

    public function testResolveIncludesTheRulesAndTheLegacyFallback()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $this->publishedTemplate('Chosen', [['field' => 'operation', 'ref_id' => $m['operation']]]);
        $this->publishedTemplate('Other');

        $resolved = Repo::resolve($m['operation'], 'perf');

        $this->assertSame('warnings', $resolved['chosen']['definition']['approval']);
        $this->assertSame('warn', $resolved['alternatives'][0]['definition']['rules']['min_laps']['severity']);
        $this->assertSame('Legacy (before templates)', $resolved['fallback']['name']);
        $this->assertSame('off', $resolved['fallback']['definition']['rules']['min_laps']['severity']);
        $this->assertSame(TimeStudyTemplateVersion::whereHas('template', fn ($q) => $q->where('system_key', 'legacy'))->value('id'), $resolved['fallback']['version_id']);
    }

    public function testResolveHasNoFallbackForEstablishSmvStudies()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();

        $this->assertNull(Repo::resolve($m['operation'], 'smv')['fallback']);
    }

    public function testResolveReturnsTheVersionToRecordWithTheStudy()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $id = $this->publishedTemplate('T', [['field' => 'operation', 'ref_id' => $m['operation']]]);
        Repo::saveDraft($id, ['definition' => ['approval' => 'always']]);
        Repo::publish($id);

        $resolved = Repo::resolve($m['operation'], 'perf');

        $this->assertSame(2, $resolved['chosen']['version_no']);
        $this->assertSame(TimeStudyTemplateVersion::where('time_study_template_id', $id)->where('version_no', 2)->value('id'), $resolved['chosen']['version_id']);
    }

    public function testResolveUsesTheLiveVersionNotAnUnpublishedDraft()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $id = $this->publishedTemplate('T', [['field' => 'operation', 'ref_id' => $m['operation']]]);
        Repo::saveDraft($id, ['conditions' => []]);

        $this->assertSame('T', Repo::resolve($m['operation'], 'perf')['chosen']['name']);
        $this->assertSame(1, Repo::resolve($m['operation'], 'perf')['chosen']['version_no']);
    }

    public function testManualOnlyTemplatesAreNeverChosenAutomaticallyButCanBeOverridden()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $id = $this->publishedTemplate('Trial');
        Repo::saveDraft($id, ['manual_only' => true]);
        Repo::publish($id);

        $resolved = Repo::resolve($m['operation'], 'perf');

        $this->assertNull($resolved['chosen']);
        $this->assertSame(['Trial'], array_column($resolved['alternatives'], 'name'));
        $this->assertTrue($resolved['alternatives'][0]['manual_only']);
    }

    public function testResolveSeparatesPurposesAndHidesTheSystemTemplate()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $this->publishedTemplate('Performance');
        $this->publishedTemplate('Set SMV', [], 'smv');

        $perf = Repo::resolve($m['operation'], 'perf');
        $smv = Repo::resolve($m['operation'], 'smv');

        $this->assertSame('Performance', $perf['chosen']['name']);
        $this->assertSame('Set SMV', $smv['chosen']['name']);
        $this->assertNotContains('Legacy (before templates)', array_column($perf['alternatives'], 'name'));
    }

    public function testResolveIgnoresArchivedAndUnpublishedTemplates()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        Repo::archive($this->publishedTemplate('Archived'));
        Repo::create(['name' => 'Draft only', 'purpose' => 'perf']);

        $resolved = Repo::resolve($m['operation'], 'perf');

        $this->assertNull($resolved['chosen']);
        $this->assertSame([], $resolved['alternatives']);
    }

    public function testResolveBreaksTiesInFavourOfTheNewestPublishedVersion()
    {
        $this->actingAsTestUser();
        $m = $this->makeMasters();
        $scope = [['field' => 'machine_type', 'ref_id' => $m['machine']]];
        $older = $this->publishedTemplate('Older', $scope);
        $newer = $this->publishedTemplate('Newer', $scope);
        DB::table('time_study_template_versions')->where('time_study_template_id', $older)->update(['published_at' => '2026-01-01 00:00:00']);
        DB::table('time_study_template_versions')->where('time_study_template_id', $newer)->update(['published_at' => '2026-02-01 00:00:00']);

        $this->assertSame('Newer', Repo::resolve($m['operation'], 'perf')['chosen']['name']);
    }

    public function testResolveForAnUnknownOperationIsAClearError()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('could not be found');

        Repo::resolve(999999, 'perf');
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function publishedTemplate(string $name, array $conditions = [], string $purpose = 'perf'): int
    {
        $id = Repo::create(['name' => $name, 'purpose' => $purpose, 'conditions' => $conditions])['id'];
        Repo::publish($id);

        return $id;
    }

    /** One operation with its full chain of masters; ids are returned so tests can build more. */
    private function makeMasters(): array
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $groupId = DB::table('product_groups')->insertGetId(['name' => 'Group '.$unique, 'code' => 'PG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Category '.$unique, 'code' => 'PC'.$unique, 'product_group_id' => $groupId, 'created_at' => now(), 'updated_at' => now()]);
        $baseOperationId = $this->makeBaseOperation();
        $machineId = $this->makeMachineType($unique);

        return [
            'unique' => $unique,
            'group' => $groupId,
            'category' => $categoryId,
            'baseOperation' => $baseOperationId,
            'machine' => $machineId,
            'operation' => $this->makeOperation($baseOperationId, $categoryId, $machineId),
        ];
    }

    private function makeBaseOperation(): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $baseCategoryId = DB::table('base_operation_categories')->insertGetId(['name' => 'Base category '.$unique, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('base_operations')->insertGetId(['name' => 'Base op '.$unique, 'code' => 'BO'.$unique, 'base_operation_category_id' => $baseCategoryId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function makeMachineType(?string $unique = null): int
    {
        $unique = $unique ?? strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $machineCategoryId = DB::table('machine_categories')->insertGetId(['name' => 'Machine category '.$unique, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('machine_types')->insertGetId(['name' => 'Machine '.$unique, 'code' => 'MT'.$unique, 'machine_category_id' => $machineCategoryId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function makeOperation(int $baseOperationId, int $productCategoryId, int $machineTypeId): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $gradeId = DB::table('operation_grades')->insertGetId(['name' => 'Grade '.$unique, 'code' => 'OG'.$unique, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('operations')->insertGetId([
            'code' => 'OP'.$unique,
            'description' => 'Operation '.$unique,
            'base_operation_id' => $baseOperationId,
            'grade_id' => $gradeId,
            'product_category_id' => $productCategoryId,
            'machine_type_id' => $machineTypeId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeFactory(): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 6));

        return DB::table('factories')->insertGetId(['name' => 'Plant '.$unique, 'code' => 'PL'.$unique, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function insertStudy(int $operationId, int $versionId, ?int $factoryId = null): int
    {
        $factoryId = $factoryId ?? DB::table('factories')->value('id') ?? $this->makeFactory();
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $hierarchyId = DB::table('management_hierarchies')->insertGetId(['name' => 'Operator '.$unique, 'seq_no' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $employeeId = DB::table('employees')->insertGetId([
            'employee_no' => 'EMP-'.$unique, 'identification_no' => 'NIC-'.$unique, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'management_hierarchy_id' => $hierarchyId, 'factory_id' => $factoryId, 'employee_status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('time_studies')->insertGetId([
            'factory_id' => $factoryId,
            'study_date' => '2026-10-01',
            'time_study_type' => 'interview_training',
            'operation_id' => $operationId,
            'employee_id' => $employeeId,
            'template_version_id' => $versionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
