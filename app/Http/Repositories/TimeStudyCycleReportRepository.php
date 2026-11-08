<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Combined cycles: one operator timed on several operations in a single pass, saved as one study per
 * operation (they share `cycle_group_id`). Shows each cycle with the efficiency of the whole pass, the time
 * lost moving between operations, and how operators do in a cycle compared with the same operations timed
 * on their own.
 *
 * Rejected studies are final and left out of every average; they still appear in the list with their status.
 */
class TimeStudyCycleReportRepository
{
    public const LIMIT = 300;
    private const SWITCH_REASON = 'OPERATION_CHANGE';

    public static function report(array $filters = []): array
    {
        $base = fn () => TimeStudyDashboardRepository::baseQuery($filters)->whereNotNull('time_studies.cycle_group_id');

        $groupIds = $base()->groupBy('time_studies.cycle_group_id')
            ->orderByRaw('MAX(time_studies.study_date) DESC')->limit(self::LIMIT)
            ->pluck('time_studies.cycle_group_id')->all();
        if ($groupIds === []) {
            return ['kpis' => self::kpis([]), 'cycles' => [], 'comparison' => self::comparison($filters)];
        }

        $studies = $base()->whereIn('time_studies.cycle_group_id', $groupIds)
            ->join('operations as o', 'o.id', '=', 'time_studies.operation_id')
            ->join('employees as e', 'e.id', '=', 'time_studies.employee_id')
            ->leftJoin('time_study_template_versions as v', 'v.id', '=', 'time_studies.template_version_id')
            ->leftJoin('time_study_templates as t', 't.id', '=', 'v.time_study_template_id')
            ->leftJoin('time_study_plan_items as pi', 'pi.id', '=', 'time_studies.time_study_plan_item_id')
            ->leftJoin('time_study_plans as pl', 'pl.id', '=', 'pi.time_study_plan_id')
            ->orderByDesc('time_studies.study_date')->orderBy('time_studies.cycle_group_id')->orderBy('time_studies.cycle_position')
            ->get([
                'time_studies.id', 'time_studies.cycle_group_id', 'time_studies.cycle_position', 'time_studies.study_date', 'time_studies.employee_id',
                'time_studies.operation_id', 'time_studies.smv', 'time_studies.avg_cycle_ms', 'time_studies.total_cycle_ms', 'time_studies.efficiency_pct',
                'time_studies.quality_score', 'time_studies.status', 'o.code', 'o.description', 'e.full_name', 'e.first_name', 'e.last_name',
                't.name as template_name', 'pl.name as plan_name',
            ]);

        $ids = $studies->pluck('id')->all();
        $laps = DB::table('time_study_laps')->whereIn('time_study_id', $ids)->where('is_excluded', false)->groupBy('time_study_id')
            ->selectRaw('time_study_id, COUNT(*) as n')->pluck('n', 'time_study_id');
        $switch = DB::table('time_study_downtimes as d')->join('time_study_downtime_reasons as r', 'r.id', '=', 'd.time_study_downtime_reason_id')
            ->whereIn('d.time_study_id', $ids)->where('r.code', self::SWITCH_REASON)->groupBy('d.time_study_id')
            ->selectRaw('d.time_study_id, SUM(d.duration_ms) as ms')->pluck('ms', 'time_study_id');

        $cycles = $studies->groupBy('cycle_group_id')->map(fn ($group) => self::present($group, $laps, $switch))->values()->all();

        return ['kpis' => self::kpis($cycles), 'cycles' => $cycles, 'comparison' => self::comparison($filters)];
    }

    private static function present($group, $laps, $switch): array
    {
        $first = $group->first();
        $operations = $group->map(fn ($s) => [
            'study_id' => (int) $s->id,
            'position' => (int) $s->cycle_position,
            'operation_id' => (int) $s->operation_id,
            'label' => $s->code ? "{$s->code} — {$s->description}" : (string) $s->description,
            'laps' => (int) ($laps[$s->id] ?? 0),
            'avg_cycle_ms' => $s->avg_cycle_ms !== null ? (int) $s->avg_cycle_ms : null,
            'efficiency_pct' => $s->efficiency_pct !== null ? round((float) $s->efficiency_pct, 1) : null,
            'quality_score' => $s->quality_score !== null ? (int) $s->quality_score : null,
            'status' => $s->status,
            'switch_ms' => (int) ($switch[$s->id] ?? 0),
        ])->sortBy('position')->values()->all();

        $kept = array_values(array_filter($operations, fn ($o) => $o['status'] !== 'rejected'));
        $rounds = $kept ? min(array_column($kept, 'laps')) : 0;
        $smvByStudy = $group->pluck('smv', 'id');

        // Total standard minutes over total actual minutes for the whole pass, counting each operation's counted laps.
        $standard = 0.0;
        $actual = 0.0;
        $complete = $kept !== [];
        foreach ($kept as $o) {
            $smv = $smvByStudy[$o['study_id']];
            if ($smv === null || $o['avg_cycle_ms'] === null || $o['laps'] === 0) {
                $complete = false;
                break;
            }
            $standard += (float) $smv * $o['laps'];
            $actual += $o['avg_cycle_ms'] * $o['laps'] / 60000;
        }
        $switchMs = array_sum(array_column($kept, 'switch_ms'));
        $qualities = array_values(array_filter(array_column($kept, 'quality_score'), fn ($q) => $q !== null));
        $totalMs = (float) $group->where('status', '!=', 'rejected')->sum(fn ($s) => (float) $s->total_cycle_ms);

        return [
            'cycle_group_id' => $first->cycle_group_id,
            'date' => (string) $first->study_date,
            'employee_id' => (int) $first->employee_id,
            'operator' => $first->full_name ?: trim($first->first_name . ' ' . $first->last_name),
            'operations' => $operations,
            'rounds' => $rounds,
            'cycle_efficiency_pct' => $complete && $actual > 0 ? round($standard / $actual * 100, 1) : null,
            'avg_quality' => $qualities ? round(array_sum($qualities) / count($qualities), 1) : null,
            'needs_review' => count(array_filter($operations, fn ($o) => $o['status'] === 'needs_review')),
            'rejected' => count(array_filter($operations, fn ($o) => $o['status'] === 'rejected')),
            'switch_ms' => (int) $switchMs,
            'switch_ms_per_round' => $rounds > 0 ? (int) round($switchMs / $rounds) : null,
            'switch_share_pct' => $totalMs > 0 ? round($switchMs / $totalMs * 100, 1) : null,
            'template_name' => $first->template_name,
            'plan_name' => $first->plan_name,
        ];
    }

    private static function kpis(array $cycles): array
    {
        $kept = array_values(array_filter($cycles, fn ($c) => $c['rounds'] > 0 || $c['avg_quality'] !== null));
        $efficiencies = array_values(array_filter(array_column($kept, 'cycle_efficiency_pct'), fn ($v) => $v !== null));
        $qualities = array_values(array_filter(array_column($kept, 'avg_quality'), fn ($v) => $v !== null));
        $shares = array_values(array_filter(array_column($kept, 'switch_share_pct'), fn ($v) => $v !== null));

        return [
            'cycles' => count($cycles),
            'studies' => array_sum(array_map(fn ($c) => count($c['operations']), $cycles)),
            'operators' => count(array_unique(array_column($cycles, 'employee_id'))),
            'avg_operations' => $cycles ? round(array_sum(array_map(fn ($c) => count($c['operations']), $cycles)) / count($cycles), 1) : null,
            'avg_cycle_efficiency_pct' => $efficiencies ? round(array_sum($efficiencies) / count($efficiencies), 1) : null,
            'avg_quality' => $qualities ? round(array_sum($qualities) / count($qualities), 1) : null,
            'avg_switch_share_pct' => $shares ? round(array_sum($shares) / count($shares), 1) : null,
            'needs_review' => array_sum(array_column($cycles, 'needs_review')),
        ];
    }

    /**
     * The same operator on the same operation, timed inside a cycle and on its own: do they do better or worse
     * in a cycle? Only pairs that have both kinds of study are compared.
     */
    private static function comparison(array $filters): array
    {
        $rows = TimeStudyDashboardRepository::baseQuery($filters)
            ->where('time_studies.purpose', 'perf')->where('time_studies.status', '!=', 'rejected')->whereNotNull('time_studies.efficiency_pct')
            ->groupBy('time_studies.employee_id', 'time_studies.operation_id')
            ->havingRaw('SUM(time_studies.cycle_group_id IS NULL) > 0 AND SUM(time_studies.cycle_group_id IS NOT NULL) > 0')
            ->selectRaw('time_studies.operation_id,
                AVG(CASE WHEN time_studies.cycle_group_id IS NULL THEN time_studies.efficiency_pct END) as single_eff,
                AVG(CASE WHEN time_studies.cycle_group_id IS NOT NULL THEN time_studies.efficiency_pct END) as cycle_eff')
            ->get();

        if ($rows->isEmpty()) {
            return ['pairs' => 0, 'avg_single_pct' => null, 'avg_cycle_pct' => null, 'difference_pts' => null, 'by_operation' => []];
        }

        $byOperation = $rows->groupBy('operation_id');
        $labels = DB::table('operations')->whereIn('id', $byOperation->keys())->get(['id', 'code', 'description'])
            ->mapWithKeys(fn ($o) => [(int) $o->id => $o->code ? "{$o->code} — {$o->description}" : (string) $o->description]);

        $list = $byOperation->map(function ($group, $operationId) use ($labels) {
            $single = $group->avg('single_eff');
            $cycle = $group->avg('cycle_eff');

            return ['operation_id' => (int) $operationId, 'label' => $labels[$operationId] ?? "Operation #{$operationId}", 'operators' => $group->count(), 'single_pct' => round($single, 1), 'cycle_pct' => round($cycle, 1), 'difference_pts' => round($cycle - $single, 1)];
        })->sortByDesc(fn ($r) => abs($r['difference_pts']))->values()->take(10)->all();

        return [
            'pairs' => $rows->count(),
            'avg_single_pct' => round($rows->avg('single_eff'), 1),
            'avg_cycle_pct' => round($rows->avg('cycle_eff'), 1),
            'difference_pts' => round($rows->avg('cycle_eff') - $rows->avg('single_eff'), 1),
            'by_operation' => $list,
        ];
    }
}
