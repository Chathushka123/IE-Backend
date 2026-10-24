<?php

namespace Tests\Unit\Support\TimeStudy;

use App\Support\TimeStudy\TemplateDefinition;
use PHPUnit\Framework\TestCase;

class TemplateDefinitionTest extends TestCase
{
    public function testStandardPresetIsValidAndCountsTowardsTheMatrix(): void
    {
        $definition = TemplateDefinition::defaults('perf');

        $this->assertSame([], TemplateDefinition::issues($definition, 'perf'));
        $this->assertTrue($definition['counts_in_matrix']);
        $this->assertSame(8, $definition['rules']['min_laps']['value']);
    }

    public function testStrictPresetBlocksAndAlwaysNeedsApproval(): void
    {
        $definition = TemplateDefinition::defaults('perf', 'strict');

        $this->assertSame('block', $definition['rules']['min_laps']['severity']);
        $this->assertSame('both', $definition['rules']['first_last']);
        $this->assertSame('always', $definition['approval']);
    }

    public function testExploratoryPresetDoesNotCountTowardsTheMatrix(): void
    {
        $definition = TemplateDefinition::defaults('perf', 'explore');

        $this->assertFalse($definition['counts_in_matrix']);
        $this->assertSame([], TemplateDefinition::issues($definition, 'perf'));
    }

    public function testEstablishSmvDefaultsAddOperatorsRatingAndAllowanceAndNeverCountTowardsTheMatrix(): void
    {
        $definition = TemplateDefinition::defaults('smv');

        $this->assertFalse($definition['counts_in_matrix']);
        $this->assertSame(3, $definition['rules']['min_operators']['value']);
        $this->assertTrue($definition['rules']['rating_required']);
        $this->assertSame(14, $definition['allowance_pct']);
        $this->assertSame([], TemplateDefinition::issues($definition, 'smv'));
    }

    public function testNormalizeFillsGapsFromTheStandardDefaults(): void
    {
        $definition = TemplateDefinition::normalize([], 'perf');

        $this->assertEquals(TemplateDefinition::defaults('perf'), $definition);
    }

    public function testNormalizeCoercesNumbersDropsUnknownKeysAndRejectsBadSeverities(): void
    {
        $definition = TemplateDefinition::normalize([
            'rules' => ['min_laps' => ['value' => '10', 'severity' => 'explode'], 'surprise' => true],
            'approval' => 'sometimes',
            'secret' => 'x',
        ], 'perf');

        $this->assertSame(10, $definition['rules']['min_laps']['value']);
        $this->assertSame('warn', $definition['rules']['min_laps']['severity']);
        $this->assertSame('warnings', $definition['approval']);
        $this->assertArrayNotHasKey('surprise', $definition['rules']);
        $this->assertArrayNotHasKey('secret', $definition);
    }

    public function testNormalizeRemovesSmvOnlySettingsFromPerformanceTemplates(): void
    {
        $definition = TemplateDefinition::normalize([
            'rules' => ['min_operators' => ['value' => 5, 'severity' => 'block'], 'rating_required' => true],
            'allowance_pct' => 20,
        ], 'perf');

        $this->assertArrayNotHasKey('min_operators', $definition['rules']);
        $this->assertArrayNotHasKey('rating_required', $definition['rules']);
        $this->assertArrayNotHasKey('allowance_pct', $definition);
    }

    public function testSmvTemplatesCanNeverCountTowardsTheMatrix(): void
    {
        $definition = TemplateDefinition::normalize(['counts_in_matrix' => true], 'smv');

        $this->assertFalse($definition['counts_in_matrix']);
    }

    public function testFieldsGetStableUniqueKeysFromTheirLabels(): void
    {
        $definition = TemplateDefinition::normalize(['fields' => [
            ['label' => 'Fabric type'],
            ['label' => 'Fabric type'],
            ['label' => '2nd shift'],
            ['label' => 'Kept', 'key' => 'kept_key'],
        ]], 'perf');

        $this->assertSame(['fabric_type', 'fabric_type_2', 'field_2nd_shift', 'kept_key'], array_column($definition['fields'], 'key'));
    }

    public function testChoiceOptionsAcceptACommaSeparatedStringAndAreTrimmedAndDeduplicated(): void
    {
        $definition = TemplateDefinition::normalize(['fields' => [
            ['label' => 'Shift', 'type' => 'choice', 'options' => ' Day, Night ,Day,, '],
        ]], 'perf');

        $this->assertSame(['Day', 'Night'], $definition['fields'][0]['options']);
    }

    public function testNonChoiceFieldsHaveNoOptions(): void
    {
        $definition = TemplateDefinition::normalize(['fields' => [['label' => 'Note', 'type' => 'text', 'options' => ['x']]]], 'perf');

        $this->assertSame([], $definition['fields'][0]['options']);
    }

    public function testMatrixTemplatesNeedAtLeastThreeLapsAndTheRuleSwitchedOn(): void
    {
        $tooFew = TemplateDefinition::defaults('perf');
        $tooFew['rules']['min_laps']['value'] = 2;
        $off = TemplateDefinition::defaults('perf');
        $off['rules']['min_laps']['severity'] = 'off';

        $this->assertStringContainsString('at least 3', TemplateDefinition::issues($tooFew, 'perf')[0]);
        $this->assertStringContainsString('at least 3', TemplateDefinition::issues($off, 'perf')[0]);
    }

    public function testTheLapFloorDoesNotApplyWhenTheTemplateDoesNotCountTowardsTheMatrix(): void
    {
        $definition = TemplateDefinition::defaults('perf', 'explore');
        $definition['rules']['min_laps'] = ['value' => 1, 'severity' => 'off'];

        $this->assertSame([], TemplateDefinition::issues($definition, 'perf'));
    }

    /** @dataProvider outOfRangeLimits */
    public function testLimitsOutsideTheirRangeAreReported(string $rule, string $key, $value): void
    {
        $definition = TemplateDefinition::defaults('perf', 'explore');
        $definition['rules'][$rule][$key] = $value;

        $this->assertNotSame([], TemplateDefinition::issues($definition, 'perf'));
    }

    public static function outOfRangeLimits(): array
    {
        return [
            'min laps zero' => ['min_laps', 'value', 0],
            'min laps too many' => ['min_laps', 'value', 101],
            'outlier too tight' => ['outlier', 'pct', 4],
            'outlier too wide' => ['outlier', 'pct', 201],
            'variation too wide' => ['variation', 'pct', 101],
            'down time zero' => ['down_time', 'pct', 0],
        ];
    }

    public function testFieldProblemsAreReportedInPlainLanguage(): void
    {
        $definition = TemplateDefinition::defaults('perf', 'explore');
        $definition['fields'] = [
            ['key' => 'a', 'label' => '', 'type' => 'text', 'required' => false, 'options' => []],
            ['key' => 'b', 'label' => 'Shift', 'type' => 'choice', 'required' => false, 'options' => []],
            ['key' => 'c', 'label' => 'shift', 'type' => 'text', 'required' => false, 'options' => []],
        ];

        $issues = implode(' ', TemplateDefinition::issues($definition, 'perf'));

        $this->assertStringContainsString('needs a name', $issues);
        $this->assertStringContainsString('“Shift” needs at least one choice', $issues);
        $this->assertStringContainsString('“shift” appears more than once', $issues);
    }

    public function testNoMoreThanTwentyFields(): void
    {
        $definition = TemplateDefinition::defaults('perf', 'explore');
        for ($i = 1; $i <= 21; $i++) {
            $definition['fields'][] = ['key' => "f$i", 'label' => "Q$i", 'type' => 'text', 'required' => false, 'options' => []];
        }

        $this->assertStringContainsString('at most 20', implode(' ', TemplateDefinition::issues($definition, 'perf')));
    }

    public function testSmvLimitsAreChecked(): void
    {
        $definition = TemplateDefinition::defaults('smv');
        $definition['rules']['min_operators']['value'] = 0;
        $definition['allowance_pct'] = 80;

        $issues = implode(' ', TemplateDefinition::issues($definition, 'smv'));

        $this->assertStringContainsString('operators', $issues);
        $this->assertStringContainsString('allowance', $issues);
    }
}
