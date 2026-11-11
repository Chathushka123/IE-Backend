<?php

namespace App\Support\TimeStudy;

/**
 * Evaluates a time study against a template version's definition. Pure: no database, no
 * auth, no clock — the same input always gives the same output.
 *
 * ADR: this is the authority. The browser runs a TypeScript port for the live checklist; both
 * are checked against tests/Fixtures/StudyEvaluator/cases.json so they cannot drift apart.
 *
 * Laps in:   [['duration_ms' => int, 'down_time_ms' => int, 'lap_no' => ?int,
 *              'excluded' => ?bool (null = apply the template's first/last rule),
 *              'outlier_kept' => ?bool]]
 * Context:   operators (int, default 1), values (field key => answer), rating (?float),
 *            smv (?float, minutes), purpose ('perf'|'smv', default 'perf').
 */
class StudyEvaluator
{
    // Limits the quality score falls back to when a template does not define its own.
    public const DEFAULT_MIN_LAPS = 8;
    public const DEFAULT_VARIATION_PCT = 20;
    public const DEFAULT_DOWN_TIME_PCT = 15;
    public const DEFAULT_OUTLIER_PCT = 30;

    // Fewest laps before an outlier ("typical lap") or variation check means anything.
    public const OUTLIER_MIN_LAPS = 4;
    public const VARIATION_MIN_LAPS = 3;

    public static function evaluate(array $definition, array $laps, array $context = []): array
    {
        $rules = $definition['rules'] ?? [];
        $purpose = $context['purpose'] ?? 'perf';
        $smv = isset($context['smv']) ? (float) $context['smv'] : null;

        $rows = self::buildLapRows($laps, $rules);
        $rows = self::flagOutliers($rows, $rules);

        $active = array_values(array_filter($rows, fn ($r) => !$r['excluded']));
        $stats = self::computeStats($active, $smv);

        $checks = self::buildChecks($definition, $rules, $rows, $active, $stats, $context, $purpose);
        $blockers = array_values(array_filter($checks, fn ($c) => $c['state'] === 'block'));
        $warnings = array_values(array_filter($checks, fn ($c) => $c['state'] === 'warn'));
        $keptCount = count(array_filter($rows, fn ($r) => $r['outlier_kept']));

        [$score, $breakdown] = self::computeQuality($rules, $rows, $active, $stats);
        [$status, $statusReason] = self::decideStatus($definition['approval'] ?? 'warnings', $warnings, $keptCount);

        return [
            'laps' => array_map(fn ($r) => [
                'lap_no' => $r['lap_no'],
                'duration_ms' => $r['duration_ms'],
                'down_time_ms' => $r['down_time_ms'],
                'net_ms' => $r['net_ms'],
                'excluded' => $r['excluded'],
                'exclusion_source' => $r['exclusion_source'],
                'outlier_flagged' => $r['outlier_flagged'],
                'outlier_kept' => $r['outlier_kept'],
                'deviation_pct' => $r['deviation_pct'],
            ], $rows),
            'stats' => $stats,
            'checks' => $checks,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'quality_score' => $score,
            'quality_breakdown' => $breakdown,
            'status' => $status,
            'status_reason' => $statusReason,
            'smv_calc' => $purpose === 'smv' ? self::computeSmv($definition, $stats, $context) : null,
        ];
    }

    /** Applies the first/last rule, honouring an explicit analyst decision per lap. */
    private static function buildLapRows(array $laps, array $rules): array
    {
        $firstLast = $rules['first_last'] ?? 'ask';
        $count = count($laps);
        $rows = [];

        foreach (array_values($laps) as $i => $lap) {
            $duration = (int) ($lap['duration_ms'] ?? 0);
            $down = (int) ($lap['down_time_ms'] ?? 0);

            // A single lap is never dropped by a rule, otherwise nothing would be left to measure.
            $ruleExcluded = $count > 1
                && ((in_array($firstLast, ['first', 'both'], true) && $i === 0)
                    || (in_array($firstLast, ['last', 'both'], true) && $i === $count - 1));

            $explicit = array_key_exists('excluded', $lap) ? $lap['excluded'] : null;
            $excluded = $explicit === null ? $ruleExcluded : (bool) $explicit;

            $rows[] = [
                'index' => $i,
                'lap_no' => $lap['lap_no'] ?? $i + 1,
                'duration_ms' => $duration,
                'down_time_ms' => $down,
                'net_ms' => $duration - $down,
                'excluded' => $excluded,
                'exclusion_source' => $excluded ? ($ruleExcluded ? 'rule' : 'analyst') : null,
                'outlier_flagged' => false,
                'outlier_kept' => false,
                'deviation_pct' => null,
                'keep_requested' => (bool) ($lap['outlier_kept'] ?? false),
            ];
        }

        return $rows;
    }

    /**
     * An "unusual" lap is far from the typical (median) lap. It needs enough laps to define
     * "typical", and the analyst must then exclude it or keep it on purpose.
     */
    private static function flagOutliers(array $rows, array $rules): array
    {
        $rule = $rules['outlier'] ?? [];
        $severity = $rule['severity'] ?? 'off';
        $limit = $rule['pct'] ?? self::DEFAULT_OUTLIER_PCT;

        $pool = array_values(array_filter($rows, fn ($r) => !$r['excluded']));
        $median = self::median(array_column($pool, 'net_ms'));

        if ($severity === 'off' || count($pool) < self::OUTLIER_MIN_LAPS || $median <= 0) {
            return $rows;
        }

        foreach ($rows as &$row) {
            if ($row['excluded']) {
                continue;
            }
            $deviation = abs($row['net_ms'] - $median) / $median * 100;
            if ($deviation > $limit) {
                $row['outlier_flagged'] = true;
                $row['outlier_kept'] = $row['keep_requested'];
                $row['deviation_pct'] = round($deviation, 1);
            }
        }
        unset($row);

        return $rows;
    }

    private static function computeStats(array $active, ?float $smv): array
    {
        $count = count($active);
        $nets = array_column($active, 'net_ms');
        $durations = array_sum(array_column($active, 'duration_ms'));
        $downs = array_sum(array_column($active, 'down_time_ms'));

        $avg = $count > 0 ? array_sum($nets) / $count : 0.0;
        $stdev = 0.0;
        if ($count > 1) {
            $stdev = sqrt(array_sum(array_map(fn ($n) => ($n - $avg) ** 2, $nets)) / $count);
        }
        $avgMinutes = $avg / 60000;

        return [
            'active_laps' => $count,
            'avg_cycle_ms' => $count > 0 ? (int) round($avg) : null,
            'fastest_cycle_ms' => $count > 0 ? min($nets) : null,
            'slowest_cycle_ms' => $count > 0 ? max($nets) : null,
            'median_cycle_ms' => $count > 0 ? self::median($nets) : null,
            'variation_pct' => $avg > 0 ? $stdev / $avg * 100 : 0.0,
            'down_time_pct' => $durations > 0 ? $downs / $durations * 100 : 0.0,
            'efficiency_pct' => ($smv !== null && $smv > 0 && $avgMinutes > 0)
                ? round($smv / $avgMinutes * 100, 2)
                : null,
        ];
    }

    private static function buildChecks(array $definition, array $rules, array $rows, array $active, array $stats, array $context, string $purpose): array
    {
        $checks = [];
        $add = function (string $id, string $label, string $state) use (&$checks) {
            $checks[] = ['id' => $id, 'label' => $label, 'state' => $state];
        };
        $violation = fn (string $severity) => $severity === 'block' ? 'block' : 'warn';

        $minLaps = $rules['min_laps'] ?? [];
        if (($minLaps['severity'] ?? 'off') !== 'off') {
            $needed = $minLaps['value'] ?? self::DEFAULT_MIN_LAPS;
            $add('laps', 'Laps', $stats['active_laps'] === 0 ? 'pending' : ($stats['active_laps'] >= $needed ? 'ok' : $violation($minLaps['severity'])));
        }

        $outlier = $rules['outlier'] ?? [];
        if (($outlier['severity'] ?? 'off') !== 'off') {
            $pool = count(array_filter($rows, fn ($r) => !$r['excluded']));
            if ($pool < self::OUTLIER_MIN_LAPS) {
                $add('outliers', 'Unusual laps', 'pending');
            } else {
                $undecided = count(array_filter($rows, fn ($r) => $r['outlier_flagged'] && !$r['outlier_kept']));
                $add('outliers', 'Unusual laps', $undecided > 0 ? $violation($outlier['severity']) : 'ok');
            }
        }

        $variation = $rules['variation'] ?? [];
        if (($variation['severity'] ?? 'off') !== 'off') {
            $limit = $variation['pct'] ?? self::DEFAULT_VARIATION_PCT;
            if ($stats['active_laps'] < self::VARIATION_MIN_LAPS) {
                $add('variation', 'Lap-to-lap variation', 'pending');
            } else {
                $add('variation', 'Lap-to-lap variation', $stats['variation_pct'] <= $limit ? 'ok' : $violation($variation['severity']));
            }
        }

        $downTime = $rules['down_time'] ?? [];
        if (($downTime['severity'] ?? 'off') !== 'off') {
            $limit = $downTime['pct'] ?? self::DEFAULT_DOWN_TIME_PCT;
            if (count($rows) === 0) {
                $add('down_time', 'Down time', 'pending');
            } else {
                $add('down_time', 'Down time', $stats['down_time_pct'] <= $limit ? 'ok' : $violation($downTime['severity']));
            }
        }

        $values = $context['values'] ?? [];
        foreach ($definition['fields'] ?? [] as $field) {
            if (empty($field['required'])) {
                continue;
            }
            $answer = $values[$field['key']] ?? null;
            $add('field:' . $field['key'], $field['label'] ?? $field['key'], ($answer === null || $answer === '') ? 'block' : 'ok');
        }

        if ($purpose === 'smv') {
            $minOperators = $rules['min_operators'] ?? [];
            if (($minOperators['severity'] ?? 'off') !== 'off') {
                $operators = (int) ($context['operators'] ?? 1);
                $add('operators', 'Operators timed', $operators >= ($minOperators['value'] ?? 1) ? 'ok' : $violation($minOperators['severity']));
            }
            if (!empty($rules['rating_required'])) {
                $rating = $context['rating'] ?? null;
                $add('rating', 'Performance rating', ($rating === null || $rating === '') ? 'block' : 'ok');
            }
        }

        return $checks;
    }

    /**
     * One fixed formula for every template, so a score means the same thing everywhere.
     * Limits come from the template's own values even when a rule is off.
     *
     * @return array{0:int,1:array<int,array{text:string,points:int}>}
     */
    private static function computeQuality(array $rules, array $rows, array $active, array $stats): array
    {
        if (count($active) === 0) {
            return [0, []];
        }

        $score = 100;
        $breakdown = [];
        $deduct = function (string $text, float $points) use (&$score, &$breakdown) {
            $points = (int) round($points);
            if ($points > 0) {
                $score -= $points;
                $breakdown[] = ['text' => $text, 'points' => -$points];
            }
        };

        $minLaps = $rules['min_laps']['value'] ?? self::DEFAULT_MIN_LAPS;
        if ($stats['active_laps'] < $minLaps) {
            $deduct(
                'Only ' . $stats['active_laps'] . ' of ' . $minLaps . ' laps',
                min(30, 30 * ($minLaps - $stats['active_laps']) / $minLaps)
            );
        }

        $unusual = count(array_filter($rows, fn ($r) => $r['outlier_flagged']));
        if ($unusual > 0) {
            $deduct($unusual . ' unusual lap' . ($unusual > 1 ? 's' : '') . ' left in', min(24, 8 * $unusual));
        }

        $variationLimit = $rules['variation']['pct'] ?? self::DEFAULT_VARIATION_PCT;
        if ($stats['variation_pct'] > $variationLimit) {
            $deduct(
                'Lap times vary ' . round($stats['variation_pct']) . '% (limit ' . $variationLimit . '%)',
                min(25, ($stats['variation_pct'] - $variationLimit) * 1.5)
            );
        }

        $downLimit = $rules['down_time']['pct'] ?? self::DEFAULT_DOWN_TIME_PCT;
        if ($stats['down_time_pct'] > $downLimit) {
            $deduct(
                'Down time ' . round($stats['down_time_pct']) . '% (limit ' . $downLimit . '%)',
                min(15, $stats['down_time_pct'] - $downLimit)
            );
        }

        return [max(0, min(100, $score)), $breakdown];
    }

    /** @return array{0:string,1:string} */
    public static function decideStatus(string $approval, array $warnings, int $keptCount): array
    {
        if ($approval === 'always') {
            return ['needs_review', 'The template requires approval for every study.'];
        }
        if ($approval === 'warnings') {
            $hasWarning = count($warnings) > 0 || $keptCount > 0;
            return $hasWarning
                ? ['needs_review', 'Sent for review because of warnings.']
                : ['approved', 'No warnings, so it is approved automatically.'];
        }

        return ['approved', 'The template approves studies automatically.'];
    }

    /** Proposed SMV = average time × rating, plus allowance. Only meaningful for 'smv' studies. */
    private static function computeSmv(array $definition, array $stats, array $context): ?array
    {
        if ($stats['active_laps'] === 0 || !$stats['avg_cycle_ms']) {
            return null;
        }

        $averageMinutes = $stats['avg_cycle_ms'] / 60000;
        $rating = (float) ($context['rating'] ?? 0) ?: 100.0;
        $allowance = (float) ($definition['allowance_pct'] ?? 0);
        $normal = $averageMinutes * $rating / 100;

        return [
            'average_minutes' => $averageMinutes,
            'rating_pct' => $rating,
            'normal_minutes' => $normal,
            'allowance_pct' => $allowance,
            'proposed_smv' => round($normal * (1 + $allowance / 100), 4),
        ];
    }

    private static function median(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }
        sort($values);
        $middle = intdiv($count, 2);

        return $count % 2 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
