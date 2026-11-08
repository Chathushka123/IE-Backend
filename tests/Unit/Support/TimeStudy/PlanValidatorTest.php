<?php

namespace Tests\Unit\Support\TimeStudy;

use App\Support\TimeStudy\PlanValidator;
use PHPUnit\Framework\TestCase;

class PlanValidatorTest extends TestCase
{
    /** The same cases drive the TypeScript port in the frontend, so the two cannot drift apart. */
    public static function cases(): array
    {
        $cases = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/StudyPlan/cases.json'), true);

        return array_combine(array_column($cases, 'name'), array_map(fn ($c) => [$c], $cases));
    }

    /** @dataProvider cases */
    public function testGoldenCase(array $case): void
    {
        $pairs = array_map(fn ($p) => ['operation_id' => $p[0], 'employee_id' => $p[1]], $case['pairs']);
        // JSON object keys arrive as strings; the validator looks names up by integer id.
        $names = ['operations' => $this->intKeys($case['names']['operations'] ?? []), 'operators' => $this->intKeys($case['names']['operators'] ?? [])];

        $this->assertSame($case['expected'], PlanValidator::problems($case['layout'], $pairs, $case['coverage'], $names, $case['requireMinimums']));
    }

    private function intKeys(array $map): array
    {
        $out = [];
        foreach ($map as $key => $value) {
            $out[(int) $key] = $value;
        }

        return $out;
    }
}
