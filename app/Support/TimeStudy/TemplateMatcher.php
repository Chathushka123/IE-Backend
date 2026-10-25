<?php

namespace App\Support\TimeStudy;

/**
 * Decides which template applies to an operation. Pure: works on plain arrays.
 *
 * Conditions on the same field mean "any of"; different fields must all match. A template with
 * no conditions matches everything. The more specific template wins: compare the narrowest field
 * each constrains, then the next narrowest (see WEIGHTS).
 */
class TemplateMatcher
{
    // ADR: powers of two, so a template that names a narrower field always beats any mix of
    // broader ones (one operation outranks every combination of category, machine and group).
    // That makes the winner predictable from the scope alone, which the builder can explain.
    // Chose this over a user-set priority number; tune the order here.
    public const WEIGHTS = [
        'operation' => 128,
        'base_operation' => 64,
        'product_category' => 32,
        'machine_type' => 16,
        'base_operation_category' => 8,
        'machine_category' => 4,
        'product_group' => 2,
        'study_type' => 1,
    ];

    /**
     * @param array<int,array{field:string,ref_id:int|string}> $conditions
     * @param array<string,int|string|null> $attributes the operation's id for each field
     */
    public static function matches(array $conditions, array $attributes): bool
    {
        foreach (self::groupByField($conditions) as $field => $allowed) {
            $actual = $attributes[$field] ?? null;
            if ($actual === null || !in_array((int) $actual, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    public static function specificity(array $conditions): int
    {
        return array_sum(array_map(fn ($field) => self::WEIGHTS[$field] ?? 0, array_keys(self::groupByField($conditions))));
    }

    /**
     * Most specific first; equal specificity goes to the most recently published.
     *
     * @param array<int,array{id:int,conditions:array,published_at:?string}> $templates
     */
    public static function rank(array $templates): array
    {
        usort($templates, function ($a, $b) {
            return [self::specificity($b['conditions']), (string) ($b['published_at'] ?? ''), $b['id']]
                <=> [self::specificity($a['conditions']), (string) ($a['published_at'] ?? ''), $a['id']];
        });

        return $templates;
    }

    /** @return array<string,int[]> */
    private static function groupByField(array $conditions): array
    {
        $grouped = [];
        foreach ($conditions as $condition) {
            if (!isset(self::WEIGHTS[$condition['field'] ?? ''])) {
                continue;
            }
            $grouped[$condition['field']][] = (int) $condition['ref_id'];
        }

        return $grouped;
    }
}
