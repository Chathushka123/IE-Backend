<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\TimeStudyRepository;
use App\TimeStudyTemplate;
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
    // createRec() — the server owns the numbers
    // ---------------------------------------------------------------

    public function testIgnoresClientSuppliedStatsAndRecomputesThemFromTheLaps()
    {
        $this->actingAsTestUser();
        $rec = $this->buildRec($this->steadyLaps(4));
        DB::table('operations')->where('id', $rec['operation_id'])->update(['smv' => 0.1]);
        $rec = array_merge($rec, [
            'smv' => 5,
            'avg_cycle_ms' => 1,
            'fastest_cycle_ms' => 1,
            'slowest_cycle_ms' => 1,
            'efficiency_pct' => 9999,
            'total_productive_ms' => 1,
        ]);

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($rec)->id)->first();

        $this->assertEquals(6000, $study->avg_cycle_ms);
        $this->assertEquals(6000, $study->fastest_cycle_ms);
        $this->assertEquals(6000, $study->slowest_cycle_ms);
        $this->assertEquals(100.00, (float) $study->efficiency_pct);
        $this->assertEquals(0.1, (float) $study->smv);
        $this->assertEquals(24200, $study->total_productive_ms);
    }

    public function testAcceptsTheSlimPayloadTheStudyScreenSends()
    {
        $this->actingAsTestUser();
        $rec = $this->buildRec($this->steadyLaps(4));
        DB::table('operations')->where('id', $rec['operation_id'])->update(['smv' => 0.1]);
        foreach (['smv', 'avg_cycle_ms', 'fastest_cycle_ms', 'slowest_cycle_ms', 'efficiency_pct', 'total_productive_ms'] as $computed) {
            unset($rec[$computed]);
        }

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($rec)->id)->first();

        $this->assertEquals(6000, $study->avg_cycle_ms);
        $this->assertEquals(100.00, (float) $study->efficiency_pct);
        $this->assertEquals(24200, $study->total_productive_ms);
    }

    public function testRecomputesNetTimeFromDurationAndDownTime()
    {
        $this->actingAsTestUser();
        $lap = ['lap_no' => 1, 'duration_ms' => 7000, 'down_time_ms' => 1000, 'net_ms' => 1, 'is_excluded' => false];

        $study = TimeStudyRepository::createRec($this->buildRec([$lap]));

        $this->assertEquals(6000, DB::table('time_study_laps')->where('time_study_id', $study->id)->value('net_ms'));
    }

    public function testRejectsALapWhoseDownTimeIsLongerThanTheLap()
    {
        $this->actingAsTestUser();
        $lap = ['lap_no' => 1, 'duration_ms' => 1000, 'down_time_ms' => 2000, 'net_ms' => 0, 'is_excluded' => false];

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('down time cannot be longer');

        TimeStudyRepository::createRec($this->buildRec([$lap]));
    }

    public function testTakesTheSmvFromTheOperationAndRecordsWhereItCameFrom()
    {
        $this->actingAsTestUser();
        $rec = $this->buildRec($this->steadyLaps(4));
        DB::table('operations')->where('id', $rec['operation_id'])->update(['smv' => 0.1]);

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($rec)->id)->first();

        $this->assertEquals('operation', $study->smv_source);
    }

    public function testAStyleOverrideSmvWinsOverTheOperationsSmv()
    {
        $this->actingAsTestUser();
        $rec = $this->buildRec($this->steadyLaps(4));
        DB::table('operations')->where('id', $rec['operation_id'])->update(['smv' => 0.1]);
        $rec['style_id'] = $this->makeStyleWithOverride($rec['operation_id'], 0.2);

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($rec)->id)->first();

        $this->assertEquals(0.2, (float) $study->smv);
        $this->assertEquals('style_override', $study->smv_source);
        $this->assertEquals(200.00, (float) $study->efficiency_pct);
    }

    public function testAStyleWithoutAnOverrideFallsBackToTheOperationsSmv()
    {
        $this->actingAsTestUser();
        $rec = $this->buildRec($this->steadyLaps(4));
        DB::table('operations')->where('id', $rec['operation_id'])->update(['smv' => 0.1]);
        $rec['style_id'] = $this->makeStyleWithOverride($this->makeOperation(), 0.9);

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($rec)->id)->first();

        $this->assertEquals('operation', $study->smv_source);
        $this->assertEquals(0.1, (float) $study->smv);
    }

    public function testAStudyWithNoSmvAnywhereHasNoEfficiency()
    {
        $this->actingAsTestUser();

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($this->buildRec($this->steadyLaps(4)))->id)->first();

        $this->assertNull($study->smv);
        $this->assertNull($study->smv_source);
        $this->assertNull($study->efficiency_pct);
    }

    public function testTakesTheCategoryAndMachineTypeFromTheOperation()
    {
        $this->actingAsTestUser();
        $rec = $this->buildRec($this->steadyLaps(4));
        $operation = DB::table('operations')->where('id', $rec['operation_id'])->first();

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($rec)->id)->first();

        $this->assertEquals($operation->product_category_id, $study->product_category_id);
        $this->assertEquals($operation->machine_type_id, $study->machine_type_id);
    }

    // ---------------------------------------------------------------
    // createRec() — templates
    // ---------------------------------------------------------------

    public function testStudiesWithoutATemplateRunUnderTheLegacyTemplate()
    {
        $this->actingAsTestUser();

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($this->buildRec($this->steadyLaps(4)))->id)->first();

        $this->assertEquals(TimeStudyTemplate::legacyVersion()->id, $study->template_version_id);
        $this->assertEquals(0, $study->template_overridden);
        $this->assertEquals('perf', $study->purpose);
        $this->assertEquals(1, $study->counts_in_matrix);
        $this->assertEquals('approved', $study->status);
    }

    public function testStoresAQualityScoreWithItsBreakdown()
    {
        $this->actingAsTestUser();

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($this->buildRec($this->steadyLaps(4)))->id)->first();

        // 4 of the standard 8 laps costs 15 points.
        $this->assertEquals(85, $study->quality_score);
        $this->assertEquals([['text' => 'Only 4 of 8 laps', 'points' => -15]], json_decode($study->quality_breakdown, true));
    }

    public function testIgnoresClientSuppliedStatusMatrixFlagAndQuality()
    {
        $this->actingAsTestUser();
        $rec = array_merge($this->buildRec($this->steadyLaps(4)), [
            'status' => 'rejected',
            'counts_in_matrix' => false,
            'quality_score' => 1,
            'purpose' => 'smv',
            'reviewed_by_id' => 1,
        ]);

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($rec)->id)->first();

        $this->assertEquals('approved', $study->status);
        $this->assertEquals(1, $study->counts_in_matrix);
        $this->assertEquals(85, $study->quality_score);
        $this->assertEquals('perf', $study->purpose);
    }

    public function testRecordsTheChosenTemplateVersionAndTheOverrideFlag()
    {
        $this->actingAsTestUser();
        $versionId = $this->makeTemplateVersion($this->definition(['counts_in_matrix' => false]));
        $rec = array_merge($this->buildRec($this->steadyLaps(4)), ['template_version_id' => $versionId, 'template_overridden' => true]);

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($rec)->id)->first();

        $this->assertEquals($versionId, $study->template_version_id);
        $this->assertEquals(1, $study->template_overridden);
        $this->assertEquals(0, $study->counts_in_matrix);
    }

    public function testRejectsAStudyThatBreaksABlockingTemplateRule()
    {
        $this->actingAsTestUser();
        $versionId = $this->makeTemplateVersion($this->definition(['rules' => $this->rules(['min_laps' => ['value' => 10, 'severity' => 'block']])]));
        $rec = array_merge($this->buildRec($this->steadyLaps(4)), ['template_version_id' => $versionId]);

        try {
            TimeStudyRepository::createRec($rec);
            $this->fail('Expected the blocking rule to reject the study.');
        } catch (GeneralException $e) {
            $this->assertStringContainsString('Laps', $e->getMessage());
        }

        $this->assertEquals(0, DB::table('time_studies')->count());
    }

    public function testRejectsAStudyMissingARequiredTemplateField()
    {
        $this->actingAsTestUser();
        $versionId = $this->makeTemplateVersion($this->definition(['fields' => [['key' => 'fabric', 'label' => 'Fabric', 'type' => 'text', 'required' => true]]]));
        $rec = array_merge($this->buildRec($this->steadyLaps(4)), ['template_version_id' => $versionId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Fabric');

        TimeStudyRepository::createRec($rec);
    }

    public function testStoresTheAnswersToTemplateFields()
    {
        $this->actingAsTestUser();
        $versionId = $this->makeTemplateVersion($this->definition(['fields' => [['key' => 'fabric', 'label' => 'Fabric', 'type' => 'text', 'required' => true]]]));
        $rec = array_merge($this->buildRec($this->steadyLaps(4)), ['template_version_id' => $versionId, 'field_values' => ['fabric' => 'Cotton pique']]);

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($rec)->id)->first();

        $this->assertEquals(['fabric' => 'Cotton pique'], json_decode($study->field_values, true));
    }

    public function testStoresWhyEachLapIsExcludedAndWhatWasDecidedAboutUnusualLaps()
    {
        $this->actingAsTestUser();
        $versionId = $this->makeTemplateVersion($this->definition(['rules' => $this->rules([
            'first_last' => 'first',
            'outlier' => ['pct' => 30, 'severity' => 'block'],
        ])]));
        $laps = [
            $this->lap(1, 9000, true),
            $this->lap(2, 6000),
            $this->lap(3, 6000),
            $this->lap(4, 6000),
            $this->lap(5, 6000),
            array_merge($this->lap(6, 12000), ['outlier_kept' => true]),
        ];
        $rec = array_merge($this->buildRec($laps), ['template_version_id' => $versionId]);

        $study = DB::table('time_studies')->where('id', TimeStudyRepository::createRec($rec)->id)->first();
        $rows = DB::table('time_study_laps')->where('time_study_id', $study->id)->orderBy('lap_no')->get();

        $this->assertEquals('rule', $rows[0]->exclusion_source);
        $this->assertNull($rows[1]->exclusion_source);
        $this->assertEquals([0, 0, 0, 0, 0, 1], $rows->pluck('outlier_flagged')->map(fn ($v) => (int) $v)->all());
        $this->assertEquals([0, 0, 0, 0, 0, 1], $rows->pluck('outlier_kept')->map(fn ($v) => (int) $v)->all());
        $this->assertEquals('needs_review', $study->status);
        $this->assertEquals([], json_decode($study->rule_flags, true));
    }

    public function testRejectsAnUndecidedUnusualLapWhenTheRuleBlocks()
    {
        $this->actingAsTestUser();
        $versionId = $this->makeTemplateVersion($this->definition(['rules' => $this->rules(['outlier' => ['pct' => 30, 'severity' => 'block']])]));
        $laps = [$this->lap(1, 6000), $this->lap(2, 6000), $this->lap(3, 6000), $this->lap(4, 6000), $this->lap(5, 12000)];
        $rec = array_merge($this->buildRec($laps), ['template_version_id' => $versionId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Unusual laps');

        TimeStudyRepository::createRec($rec);
    }

    public function testRejectsADraftTemplateVersion()
    {
        $this->actingAsTestUser();
        $versionId = $this->makeTemplateVersion($this->definition(), 'perf', 'draft');
        $rec = array_merge($this->buildRec($this->steadyLaps(4)), ['template_version_id' => $versionId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('not published');

        TimeStudyRepository::createRec($rec);
    }

    public function testRejectsAnArchivedTemplate()
    {
        $this->actingAsTestUser();
        $versionId = $this->makeTemplateVersion($this->definition(), 'perf', 'published', now());
        $rec = array_merge($this->buildRec($this->steadyLaps(4)), ['template_version_id' => $versionId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('archived');

        TimeStudyRepository::createRec($rec);
    }

    public function testRejectsAnUnknownTemplateVersion()
    {
        $this->actingAsTestUser();
        $rec = array_merge($this->buildRec($this->steadyLaps(4)), ['template_version_id' => 999999]);

        $this->expectException(GeneralException::class);

        TimeStudyRepository::createRec($rec);
    }

    public function testEstablishSmvTemplatesCannotBeUsedForASingleStudy()
    {
        $this->actingAsTestUser();
        $versionId = $this->makeTemplateVersion($this->definition(), 'smv');
        $rec = array_merge($this->buildRec($this->steadyLaps(4)), ['template_version_id' => $versionId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('SMV session');

        TimeStudyRepository::createRec($rec);
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

    private function steadyLaps(int $count, int $netMs = 6000): array
    {
        return array_map(fn ($n) => $this->lap($n, $netMs), range(1, $count));
    }

    private function rules(array $overrides = []): array
    {
        return array_merge([
            'min_laps' => ['value' => 4, 'severity' => 'off'],
            'outlier' => ['pct' => 30, 'severity' => 'off'],
            'variation' => ['pct' => 20, 'severity' => 'off'],
            'down_time' => ['pct' => 15, 'severity' => 'off'],
            'first_last' => 'ask',
        ], $overrides);
    }

    private function definition(array $overrides = []): array
    {
        return array_merge([
            'rules' => $this->rules(),
            'fields' => [],
            'counts_in_matrix' => true,
            'approval' => 'warnings',
        ], $overrides);
    }

    private function makeTemplateVersion(array $definition, string $purpose = 'perf', string $status = 'published', $archivedAt = null): int
    {
        $templateId = DB::table('time_study_templates')->insertGetId([
            'name' => 'Template '.uniqid(),
            'purpose' => $purpose,
            'archived_at' => $archivedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('time_study_template_versions')->insertGetId([
            'time_study_template_id' => $templateId,
            'version_no' => $status === 'published' ? 1 : null,
            'status' => $status,
            'definition' => json_encode($definition),
            'published_at' => $status === 'published' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeStyleWithOverride(int $operationId, float $smv): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $productCategoryId = DB::table('operations')->where('id', $operationId)->value('product_category_id');
        $customerId = DB::table('customers')->insertGetId([
            'description' => 'Customer '.$unique,
            'code' => 'CU'.$unique,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $seasonId = DB::table('seasons')->insertGetId([
            'name' => 'Season '.$unique,
            'code' => 'SE'.$unique,
            'customer_id' => $customerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $styleId = DB::table('styles')->insertGetId([
            'name' => 'Style '.$unique,
            'style_code' => 'ST'.$unique,
            'product_category_id' => $productCategoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('style_operations')->insert([
            'style_id' => $styleId,
            'operation_id' => $operationId,
            'sequence_no' => 1,
            'smv' => $smv,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $styleId;
    }
}
