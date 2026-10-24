<?php

namespace Tests\Unit\Support\TimeStudy;

use App\Support\TimeStudy\StudyEvaluator;
use PHPUnit\Framework\TestCase;

/**
 * Pure logic, so no framework or database. The golden cases in
 * tests/Fixtures/StudyEvaluator/cases.json are the contract shared with the TypeScript port the
 * study screen uses for its live checklist: both languages must produce these same results.
 */
class StudyEvaluatorTest extends TestCase
{
    private const FLOAT_DELTA = 0.01;

    public static function goldenCases(): array
    {
        $cases = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/StudyEvaluator/cases.json'), true);
        $named = [];
        foreach ($cases as $case) {
            $named[$case['name']] = [$case];
        }

        return $named;
    }

    /** @dataProvider goldenCases */
    public function testMatchesGoldenCase(array $case): void
    {
        $result = StudyEvaluator::evaluate($case['definition'], $case['laps'], $case['context']);
        $expected = $case['expected'];

        foreach ($expected['stats'] ?? [] as $key => $value) {
            $this->assertArrayHasKey($key, $result['stats']);
            $this->assertSameNumber($value, $result['stats'][$key], "stats.$key");
        }

        if (array_key_exists('checks', $expected)) {
            $actual = array_column($result['checks'], 'state', 'id');
            $this->assertEquals($expected['checks'], $actual, 'checks');
        }
        if (array_key_exists('blockers', $expected)) {
            $this->assertCount($expected['blockers'], $result['blockers'], 'blockers');
        }
        if (array_key_exists('warnings', $expected)) {
            $this->assertCount($expected['warnings'], $result['warnings'], 'warnings');
        }
        if (array_key_exists('quality_score', $expected)) {
            $this->assertSame($expected['quality_score'], $result['quality_score'], 'quality_score');
        }
        if (array_key_exists('quality_breakdown', $expected)) {
            $this->assertSame($expected['quality_breakdown'], $result['quality_breakdown'], 'quality_breakdown');
        }
        if (array_key_exists('status', $expected)) {
            $this->assertSame($expected['status'], $result['status'], 'status');
        }
        foreach ($expected['laps'] ?? [] as $i => $expectedLap) {
            foreach ($expectedLap as $key => $value) {
                $this->assertSameNumber($value, $result['laps'][$i][$key], "laps[$i].$key");
            }
        }
        if (array_key_exists('smv_calc', $expected)) {
            if ($expected['smv_calc'] === null) {
                $this->assertNull($result['smv_calc'], 'smv_calc');
            } else {
                foreach ($expected['smv_calc'] as $key => $value) {
                    $this->assertEqualsWithDelta($value, $result['smv_calc'][$key], 0.00005, "smv_calc.$key");
                }
            }
        }
    }

    public function testIsDeterministic(): void
    {
        $case = self::goldenCases()['undecided unusual lap blocks and lowers quality'][0];

        $this->assertSame(
            StudyEvaluator::evaluate($case['definition'], $case['laps'], $case['context']),
            StudyEvaluator::evaluate($case['definition'], $case['laps'], $case['context'])
        );
    }

    public function testNetTimeIsDurationMinusDownTimeAndIgnoresAnyClientNetValue(): void
    {
        $result = StudyEvaluator::evaluate(['rules' => []], [
            ['duration_ms' => 6000, 'down_time_ms' => 1000, 'net_ms' => 1],
        ]);

        $this->assertSame(5000, $result['laps'][0]['net_ms']);
        $this->assertSame(5000, $result['stats']['avg_cycle_ms']);
    }

    public function testMissingRulesBehaveAsOffAndUseDefaultLimitsForQuality(): void
    {
        $result = StudyEvaluator::evaluate([], [
            ['duration_ms' => 6000, 'down_time_ms' => 0],
            ['duration_ms' => 6000, 'down_time_ms' => 0],
        ]);

        $this->assertSame([], $result['checks']);
        // 2 of the default 8 laps: 30 * 6 / 8 = 22.5, rounded to 23.
        $this->assertSame(77, $result['quality_score']);
        $this->assertSame('approved', $result['status']);
    }

    public function testAnEmptyLapListHasNoStatsAndZeroQuality(): void
    {
        $result = StudyEvaluator::evaluate(['rules' => ['min_laps' => ['value' => 3, 'severity' => 'block']]], []);

        $this->assertSame(0, $result['stats']['active_laps']);
        $this->assertNull($result['stats']['avg_cycle_ms']);
        $this->assertSame(0, $result['quality_score']);
    }

    public function testLapNumbersFallBackToPositionWhenNotSupplied(): void
    {
        $result = StudyEvaluator::evaluate([], [
            ['duration_ms' => 1000],
            ['duration_ms' => 1000, 'lap_no' => 7],
        ]);

        $this->assertSame([1, 7], array_column($result['laps'], 'lap_no'));
    }

    public function testQualityDeductionsAreCappedAndNeverGoBelowZero(): void
    {
        $laps = [];
        foreach ([1000, 1000, 1000, 1000, 90000, 90000, 90000, 90000] as $net) {
            $laps[] = ['duration_ms' => $net + 50000, 'down_time_ms' => 50000];
        }
        $result = StudyEvaluator::evaluate(['rules' => [
            'min_laps' => ['value' => 100, 'severity' => 'off'],
            'outlier' => ['pct' => 10, 'severity' => 'warn'],
            'variation' => ['pct' => 1, 'severity' => 'warn'],
            'down_time' => ['pct' => 1, 'severity' => 'warn'],
        ]], $laps);

        $this->assertGreaterThanOrEqual(0, $result['quality_score']);
        $this->assertLessThanOrEqual(100, $result['quality_score']);
        // shortfall (30) + unusual (24 cap) + variation (25 cap) + down time (15 cap) = 94 at most.
        $this->assertGreaterThanOrEqual(6, $result['quality_score']);
    }

    private function assertSameNumber($expected, $actual, string $label): void
    {
        if ($expected === null) {
            $this->assertNull($actual, $label);
        } elseif (is_float($expected) || (is_numeric($expected) && is_float($actual))) {
            $this->assertEqualsWithDelta($expected, $actual, self::FLOAT_DELTA, $label);
        } else {
            $this->assertSame($expected, $actual, $label);
        }
    }
}
