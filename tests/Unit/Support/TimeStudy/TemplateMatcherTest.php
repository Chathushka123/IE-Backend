<?php

namespace Tests\Unit\Support\TimeStudy;

use App\Support\TimeStudy\TemplateMatcher;
use PHPUnit\Framework\TestCase;

class TemplateMatcherTest extends TestCase
{
    private const OPERATION = ['operation' => 10, 'base_operation' => 20, 'base_operation_category' => 30, 'machine_type' => 40, 'machine_category' => 45, 'product_category' => 50, 'product_group' => 60, 'study_type' => 2];

    private function cond(string $field, int $id): array
    {
        return ['field' => $field, 'ref_id' => $id];
    }

    public function testNoConditionsMatchesEverything(): void
    {
        $this->assertTrue(TemplateMatcher::matches([], self::OPERATION));
    }

    public function testAllFieldsMustMatch(): void
    {
        $this->assertTrue(TemplateMatcher::matches([$this->cond('machine_type', 40), $this->cond('product_group', 60)], self::OPERATION));
        $this->assertFalse(TemplateMatcher::matches([$this->cond('machine_type', 40), $this->cond('product_group', 61)], self::OPERATION));
    }

    public function testSeveralValuesOnTheSameFieldMeanAnyOf(): void
    {
        $conditions = [$this->cond('machine_type', 41), $this->cond('machine_type', 40)];

        $this->assertTrue(TemplateMatcher::matches($conditions, self::OPERATION));
        $this->assertFalse(TemplateMatcher::matches([$this->cond('machine_type', 41), $this->cond('machine_type', 42)], self::OPERATION));
    }

    public function testStringIdsFromTheDatabaseStillMatch(): void
    {
        $this->assertTrue(TemplateMatcher::matches([['field' => 'operation', 'ref_id' => '10']], ['operation' => '10']));
    }

    public function testAnOperationMissingTheFieldNeverMatchesACondition(): void
    {
        $this->assertFalse(TemplateMatcher::matches([$this->cond('machine_type', 40)], ['operation' => 10]));
    }

    public function testUnknownFieldsAreIgnored(): void
    {
        $this->assertTrue(TemplateMatcher::matches([['field' => 'colour', 'ref_id' => 1]], self::OPERATION));
        $this->assertSame(0, TemplateMatcher::specificity([['field' => 'colour', 'ref_id' => 1]]));
    }

    public function testSpecificityIsTheSumOfTheConstrainedFieldsWeightsCountingEachFieldOnce(): void
    {
        $this->assertSame(0, TemplateMatcher::specificity([]));
        $this->assertSame(128, TemplateMatcher::specificity([$this->cond('operation', 1)]));
        $this->assertSame(32, TemplateMatcher::specificity([$this->cond('product_category', 1), $this->cond('product_category', 2)]));
        $this->assertSame(2 + 16, TemplateMatcher::specificity([$this->cond('product_group', 1), $this->cond('machine_type', 2)]));
    }

    public function testASpecificOperationOutranksAnyCombinationOfBroaderFields(): void
    {
        $broad = ['id' => 1, 'published_at' => '2026-01-01', 'conditions' => [$this->cond('product_category', 1), $this->cond('machine_type', 1)]];
        $specific = ['id' => 2, 'published_at' => '2026-01-01', 'conditions' => [$this->cond('operation', 1)]];

        $this->assertSame([2, 1], array_column(TemplateMatcher::rank([$broad, $specific]), 'id'));
    }

    public function testEachFieldOutranksEveryNarrowerCombinationBelowIt(): void
    {
        $fields = array_keys(TemplateMatcher::WEIGHTS);
        foreach ($fields as $i => $field) {
            $below = array_slice($fields, $i + 1);
            $this->assertGreaterThan(
                TemplateMatcher::specificity(array_map(fn ($f) => $this->cond($f, 1), $below)),
                TemplateMatcher::specificity([$this->cond($field, 1)]),
                "$field must outrank all of: " . implode(', ', $below)
            );
        }
    }

    public function testAStudyTypeConditionNeedsTheStudyTypeToBeKnown(): void
    {
        $this->assertTrue(TemplateMatcher::matches([$this->cond('study_type', 2)], self::OPERATION));
        $this->assertFalse(TemplateMatcher::matches([$this->cond('study_type', 1)], self::OPERATION));
        $this->assertFalse(TemplateMatcher::matches([$this->cond('study_type', 2)], ['study_type' => null] + self::OPERATION));
    }

    public function testEqualSpecificityGoesToTheMostRecentlyPublished(): void
    {
        $old = ['id' => 1, 'published_at' => '2026-01-01 10:00:00', 'conditions' => [$this->cond('machine_type', 1)]];
        $new = ['id' => 2, 'published_at' => '2026-03-01 10:00:00', 'conditions' => [$this->cond('machine_type', 2)]];

        $this->assertSame([2, 1], array_column(TemplateMatcher::rank([$old, $new]), 'id'));
    }

    public function testRankingIsStableWhenEverythingIsEqual(): void
    {
        $a = ['id' => 1, 'published_at' => null, 'conditions' => []];
        $b = ['id' => 2, 'published_at' => null, 'conditions' => []];

        $this->assertSame([2, 1], array_column(TemplateMatcher::rank([$a, $b]), 'id'));
    }
}
