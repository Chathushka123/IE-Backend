<?php

namespace Tests\Unit\Support\TimeStudy;

use App\Support\TimeStudy\SmvSession;
use App\Support\TimeStudy\StudyEvaluator;
use PHPUnit\Framework\TestCase;

class SmvSessionTest extends TestCase
{
    public static function goldenCases(): array
    {
        $cases = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/StudyEvaluator/smvSessions.json'), true);

        return array_combine(array_column($cases, 'name'), array_map(fn ($c) => [$c], $cases));
    }

    /** @dataProvider goldenCases */
    public function testMatchesGoldenCase(array $case): void
    {
        $result = SmvSession::propose($case['normal_minutes'], $case['allowance_pct'], $case['current_smv']);

        foreach ($case['expected'] as $key => $value) {
            if ($value === null) {
                $this->assertNull($result[$key], $key);
            } elseif (is_float($value)) {
                $this->assertEqualsWithDelta($value, $result[$key], 0.00005, $key);
            } else {
                $this->assertSame($value, $result[$key], $key);
            }
        }
    }

    public function testAnOperatorWithMoreLapsDoesNotCountForMore(): void
    {
        // Two operators: one with a normal time of 0.10, one of 0.20. The mean is 0.15 however many laps each recorded.
        $result = SmvSession::propose([0.10, 0.20], 0, null);

        $this->assertEqualsWithDelta(0.15, $result['normal_minutes'], 1e-9);
    }

    public function testTheSessionNeedsReviewWhenAnyOperatorsRunWarned(): void
    {
        $clean = $this->evaluation(withWarning: false, kept: 0);
        $warned = $this->evaluation(withWarning: true, kept: 0);

        [$status] = SmvSession::decideStatus('warnings', [$clean, $warned]);

        $this->assertSame('needs_review', $status);
    }

    public function testAKeptUnusualLapAlsoNeedsReviewUnderTheWarningsPolicy(): void
    {
        [$status] = SmvSession::decideStatus('warnings', [$this->evaluation(false, 1)]);

        $this->assertSame('needs_review', $status);
    }

    public function testACleanSessionIsApprovedUnderTheWarningsPolicyButNotWhenApprovalIsAlwaysRequired(): void
    {
        $runs = [$this->evaluation(false, 0), $this->evaluation(false, 0)];

        $this->assertSame('approved', SmvSession::decideStatus('warnings', $runs)[0]);
        $this->assertSame('needs_review', SmvSession::decideStatus('always', $runs)[0]);
        $this->assertSame('approved', SmvSession::decideStatus('never', [$this->evaluation(true, 1)])[0]);
    }

    public function testAnEmptySessionDoesNotCrashTheStatusDecision(): void
    {
        $this->assertSame('approved', SmvSession::decideStatus('warnings', [])[0]);
    }

    private function evaluation(bool $withWarning, int $kept): array
    {
        $laps = array_fill(0, 4, ['outlier_kept' => false]);
        for ($i = 0; $i < $kept; $i++) {
            $laps[$i]['outlier_kept'] = true;
        }

        return [
            'warnings' => $withWarning ? [['id' => 'variation', 'label' => 'Lap-to-lap variation', 'state' => 'warn']] : [],
            'laps' => $laps,
        ];
    }
}
