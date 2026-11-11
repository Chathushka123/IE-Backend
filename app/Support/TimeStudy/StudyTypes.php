<?php

namespace App\Support\TimeStudy;

/**
 * The kinds of time study (`time_studies.time_study_type`) as numeric ids, so a template can scope
 * to one through the same `field` + `ref_id` condition rows as every other kind of scope.
 *
 * ADR: ids for an enum instead of a second condition column, so the matcher, the indexes and the
 * clone/publish code stay untouched. Append new types with the next id; never renumber.
 */
class StudyTypes
{
    public const IDS = ['interview_training' => 1, 'production_floor' => 2];

    public const LABELS = [1 => 'Interview & Training', 2 => 'Production Floor'];

    public static function idOf(?string $type): ?int
    {
        return self::IDS[$type ?? ''] ?? null;
    }

    public static function exists(int $id): bool
    {
        return isset(self::LABELS[$id]);
    }

    /** @return int[] */
    public static function all(): array
    {
        return array_values(self::IDS);
    }
}
