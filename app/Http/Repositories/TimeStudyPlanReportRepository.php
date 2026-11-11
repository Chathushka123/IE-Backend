<?php

namespace App\Http\Repositories;

use App\Support\FactoryContext;
use App\Support\TimeStudy\TemplateDefinition;
use Illuminate\Support\Facades\DB;

/**
 * How study plans went: progress against what was planned, whether each operation got the operators its
 * template asks for, and what each analyst recorded. Read-only, built from the plans, their pairs and the
 * studies recorded against them, so it always agrees with the plan screen.
 *
 * A rejected study is final and does not count (the same rule the plan screen uses), so a pair whose only
 * study was rejected shows as still to do.
 */
class TimeStudyPlanReportRepository
{
    /** Newest plans first; a report is for looking, and a hundred plans is plenty to look at. */
    public const LIMIT = 100;

    public static function report(array $filters = []): array
    {
        $plans = self::plans($filters);
        if ($plans->isEmpty()) {
            return ['kpis' => self::kpis([], 0, 0), 'plans' => [], 'analysts' => []];
        }

        $items = DB::table('time_study_plan_items as i')
            ->join('operations as o', 'o.id', '=', 'i.operation_id')
            ->join('employees as e', 'e.id', '=', 'i.employee_id')
            ->whereIn('i.time_study_plan_id', $plans->pluck('id'))
            ->orderBy('i.operation_id')->orderBy('i.id')
            ->get(['i.id', 'i.time_study_plan_id', 'i.operation_id', 'i.employee_id', 'i.status', 'o.code', 'o.description', 'e.full_name', 'e.first_name', 'e.last_name'])
            ->groupBy('time_study_plan_id');

        $itemIds = $items->flatten()->pluck('id')->all();
        $stats = $itemIds === [] ? collect() : DB::table('time_studies')
            ->whereIn('time_study_plan_item_id', $itemIds)
            ->groupBy('time_study_plan_item_id')
            ->selectRaw("time_study_plan_item_id as item_id,
                SUM(status != 'rejected') as counted,
                SUM(status = 'rejected') as rejected,
                SUM(status = 'needs_review') as needs_review,
                AVG(CASE WHEN status != 'rejected' THEN quality_score END) as quality,
                AVG(CASE WHEN status != 'rejected' THEN efficiency_pct END) as efficiency")
            ->get()->keyBy('item_id');

        $rows = $plans->map(fn ($plan) => self::present($plan, $items->get($plan->id, collect()), $stats))->values()->all();

        return [
            'kpis' => self::kpis($rows, count($rows), count($itemIds)),
            'plans' => $rows,
            'analysts' => self::analysts($itemIds),
        ];
    }

    // ---------------------------------------------------------------
    // Plans in scope
    // ---------------------------------------------------------------

    private static function plans(array $filters)
    {
        $q = DB::table('time_study_plans as p')
            ->join('time_study_template_versions as v', 'v.id', '=', 'p.template_version_id')
            ->join('time_study_templates as t', 't.id', '=', 'v.time_study_template_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.created_by_id')
            ->leftJoin('styles as s', 's.id', '=', 'p.style_id')
            ->leftJoin('factories as f', 'f.id', '=', 'p.factory_id')
            ->select('p.*', 'v.version_no', 'v.definition', 't.name as template_name', 'u.name as created_by', 's.name as style_name', 'f.name as factory_name');

        if (!FactoryContext::isBypassed() && FactoryContext::isResolved()) {
            $q->whereIn('p.factory_id', FactoryContext::ids() ?: [0]);
        }
        // A plan's date is when it started running, or when it was made if it never did.
        if (!empty($filters['study_date_from'])) {
            $q->whereRaw('DATE(COALESCE(p.activated_at, p.created_at)) >= ?', [$filters['study_date_from']]);
        }
        if (!empty($filters['study_date_to'])) {
            $q->whereRaw('DATE(COALESCE(p.activated_at, p.created_at)) <= ?', [$filters['study_date_to']]);
        }
        foreach (['plan_statuses' => 'p.status', 'time_study_types' => 'p.time_study_type', 'factory_ids' => 'p.factory_id', 'style_ids' => 'p.style_id', 'created_by_ids' => 'p.created_by_id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $q->whereIn($column, (array) $filters[$key]);
            }
        }

        // Filters on what a plan contains: any of its pairs (or studies) matching is enough.
        $pairs = fn ($condition) => $q->whereExists(fn ($x) => $condition($x->select(DB::raw(1))->from('time_study_plan_items as pi')->whereColumn('pi.time_study_plan_id', 'p.id')));
        if (!empty($filters['employee_ids'])) {
            $pairs(fn ($x) => $x->whereIn('pi.employee_id', (array) $filters['employee_ids']));
        }
        if (!empty($filters['operation_ids'])) {
            $pairs(fn ($x) => $x->whereIn('pi.operation_id', (array) $filters['operation_ids']));
        }
        foreach (['product_category_ids' => 'o.product_category_id', 'machine_type_ids' => 'o.machine_type_id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $pairs(fn ($x) => $x->join('operations as o', 'o.id', '=', 'pi.operation_id')->whereIn($column, (array) $filters[$key]));
            }
        }
        if (!empty($filters['conducted_by_ids'])) {
            $pairs(fn ($x) => $x->whereExists(fn ($y) => $y->select(DB::raw(1))->from('time_studies as ts')->whereColumn('ts.time_study_plan_item_id', 'pi.id')->whereIn('ts.conducted_by_id', (array) $filters['conducted_by_ids'])));
        }

        return $q->orderByRaw('COALESCE(p.activated_at, p.created_at) DESC')->orderByDesc('p.id')->limit(self::LIMIT)->get();
    }

    // ---------------------------------------------------------------
    // One plan
    // ---------------------------------------------------------------

    private static function present($plan, $items, $stats): array
    {
        $layout = TemplateDefinition::layoutOf(json_decode((string) $plan->definition, true) ?: []);
        $target = (int) $plan->target_per_pair;
        $wanted = $items->where('status', 'pending');
        $counted = fn ($item) => (int) ($stats->get($item->id)->counted ?? 0);
        $isDone = fn ($item) => $counted($item) >= $target;

        // Each operation needs this many operators to have finished their studies for the template's layout to be met.
        $required = $layout['operators_per_operation']['mode'] === 'several' ? (int) $layout['operators_per_operation']['min'] : 1;
        $operations = $wanted->groupBy('operation_id')->map(function ($group) use ($isDone, $required) {
            $done = $group->filter($isDone)->count();
            $first = $group->first();

            return [
                'operation_id' => (int) $first->operation_id,
                'label' => $first->code ? "{$first->code} — {$first->description}" : (string) $first->description,
                'operators_planned' => $group->count(),
                'operators_done' => $done,
                'required' => $required,
                'met' => $done >= $required,
            ];
        })->values()->all();

        $operators = $wanted->groupBy('employee_id')->map(function ($group) use ($counted, $isDone, $target) {
            $first = $group->first();

            return [
                'employee_id' => (int) $first->employee_id,
                'name' => $first->full_name ?: trim($first->first_name . ' ' . $first->last_name),
                'pairs' => $group->count(),
                'pairs_done' => $group->filter($isDone)->count(),
                'studies_done' => $group->sum(fn ($i) => min($counted($i), $target)),
                'studies_target' => $group->count() * $target,
            ];
        })->values()->all();

        $studiesTarget = $wanted->count() * $target;
        $studiesDone = $wanted->sum(fn ($i) => min($counted($i), $target));
        $countedTotal = $items->sum($counted);

        return [
            'id' => (int) $plan->id,
            'name' => $plan->name,
            'status' => $plan->status,
            'template_name' => $plan->template_name,
            'version_no' => $plan->version_no !== null ? (int) $plan->version_no : null,
            'time_study_type' => $plan->time_study_type,
            'style_name' => $plan->style_name,
            'factory_name' => $plan->factory_name,
            'created_by' => $plan->created_by,
            'started_at' => $plan->activated_at,
            'ended_at' => $plan->completed_at,
            'layout' => $layout,
            'target_per_pair' => $target,
            'pairs' => $wanted->count(),
            'pairs_done' => $wanted->filter($isDone)->count(),
            'skipped' => $items->where('status', 'skipped')->count(),
            'studies_target' => $studiesTarget,
            'studies_done' => (int) $studiesDone,
            'completion_pct' => $studiesTarget > 0 ? round($studiesDone / $studiesTarget * 100, 1) : null,
            'avg_quality' => $countedTotal > 0 ? round($items->sum(fn ($i) => (float) ($stats->get($i->id)->quality ?? 0) * $counted($i)) / $countedTotal, 1) : null,
            'avg_efficiency' => $countedTotal > 0 ? round($items->sum(fn ($i) => (float) ($stats->get($i->id)->efficiency ?? 0) * $counted($i)) / $countedTotal, 1) : null,
            'needs_review' => (int) $items->sum(fn ($i) => (int) ($stats->get($i->id)->needs_review ?? 0)),
            'rejected' => (int) $items->sum(fn ($i) => (int) ($stats->get($i->id)->rejected ?? 0)),
            'operations' => $operations,
            'operators' => $operators,
            // A draft has recorded nothing and a cancelled plan was dropped, so neither is "short" of anything.
            'operations_unmet' => in_array($plan->status, ['active', 'completed'], true) ? count(array_filter($operations, fn ($o) => !$o['met'])) : 0,
        ];
    }

    // ---------------------------------------------------------------
    // Totals
    // ---------------------------------------------------------------

    private static function kpis(array $rows, int $plans, int $pairs): array
    {
        // Progress is judged on plans that were ever started: a draft has done nothing yet, a cancelled plan was dropped.
        $judged = array_values(array_filter($rows, fn ($r) => in_array($r['status'], ['active', 'completed'], true)));
        $target = array_sum(array_column($judged, 'studies_target'));
        $done = array_sum(array_column($judged, 'studies_done'));
        $withQuality = array_values(array_filter($judged, fn ($r) => $r['avg_quality'] !== null));

        return [
            'plans' => $plans,
            'running' => count(array_filter($rows, fn ($r) => $r['status'] === 'active')),
            'finished' => count(array_filter($rows, fn ($r) => $r['status'] === 'completed')),
            'drafts' => count(array_filter($rows, fn ($r) => $r['status'] === 'draft')),
            'cancelled' => count(array_filter($rows, fn ($r) => $r['status'] === 'cancelled')),
            'planned_studies' => (int) $target,
            'recorded_studies' => (int) $done,
            'completion_pct' => $target > 0 ? round($done / $target * 100, 1) : null,
            'skipped_pairs' => array_sum(array_column($rows, 'skipped')),
            'operations_unmet' => array_sum(array_column($judged, 'operations_unmet')),
            'needs_review' => array_sum(array_column($rows, 'needs_review')),
            'avg_quality' => $withQuality ? round(array_sum(array_column($withQuality, 'avg_quality')) / count($withQuality), 1) : null,
            'pairs' => $pairs,
        ];
    }

    /** Who recorded the studies of these plans. */
    private static function analysts(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return DB::table('time_studies as ts')
            ->leftJoin('users as u', 'u.id', '=', 'ts.conducted_by_id')
            ->whereIn('ts.time_study_plan_item_id', $itemIds)
            ->where('ts.status', '!=', 'rejected')
            ->groupBy('ts.conducted_by_id', 'u.name')
            ->orderByRaw('COUNT(*) DESC')
            ->selectRaw('ts.conducted_by_id as user_id, u.name as name, COUNT(*) as studies, AVG(ts.quality_score) as avg_quality')
            ->get()
            ->map(fn ($r) => ['user_id' => $r->user_id !== null ? (int) $r->user_id : null, 'name' => $r->name ?? 'Unknown', 'studies' => (int) $r->studies, 'avg_quality' => $r->avg_quality !== null ? round((float) $r->avg_quality, 1) : null])
            ->all();
    }
}
