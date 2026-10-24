<?php

namespace App\Support\TimeStudy;

/**
 * The numbers of an SMV study that spans several operators. Pure.
 *
 * Each operator's normal time is their average net lap times their own performance rating. The
 * session's normal time is the plain mean of those, so an operator who happened to record more laps
 * does not count for more. The proposed SMV adds the template's allowance on top.
 *
 * The TypeScript port the study screen uses (smvSession.ts) is checked against the same cases in
 * tests/Fixtures/StudyEvaluator/smvSessions.json.
 */
class SmvSession
{
    /**
     * @param float[] $normalMinutes one normal time per operator, in minutes
     */
    public static function propose(array $normalMinutes, float $allowancePct, ?float $currentSmv): array
    {
        $operators = count($normalMinutes);
        if ($operators === 0) {
            return ['operators' => 0, 'normal_minutes' => null, 'allowance_pct' => $allowancePct, 'proposed_smv' => null, 'current_smv' => $currentSmv, 'change_pct' => null];
        }

        $normal = array_sum($normalMinutes) / $operators;
        $proposed = round($normal * (1 + $allowancePct / 100), 4);

        return [
            'operators' => $operators,
            'normal_minutes' => $normal,
            'allowance_pct' => $allowancePct,
            'proposed_smv' => $proposed,
            'current_smv' => $currentSmv,
            'change_pct' => ($currentSmv !== null && $currentSmv > 0) ? round(($proposed / $currentSmv - 1) * 100, 1) : null,
        ];
    }

    /**
     * One status for the whole session: it needs review if the template always asks for it, or if any
     * operator's run raised a warning or kept an unusual lap.
     *
     * @param array<int,array> $evaluations the StudyEvaluator result of each run
     * @return array{0:string,1:string}
     */
    public static function decideStatus(string $approval, array $evaluations): array
    {
        $warnings = array_merge(...array_map(fn ($e) => $e['warnings'], $evaluations ?: [[ 'warnings' => [] ]]));
        $kept = array_sum(array_map(fn ($e) => count(array_filter($e['laps'], fn ($lap) => $lap['outlier_kept'])), $evaluations));

        return StudyEvaluator::decideStatus($approval, $warnings, $kept);
    }
}
