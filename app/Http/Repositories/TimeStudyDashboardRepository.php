<?php

namespace App\Http\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\FactoryContext;

class TimeStudyDashboardRepository
{
    /**
     * DB::table('time_studies') pre-scoped to the current request's factory context and
     * the report's filter set. Raw query builder (not the TimeStudy Eloquent model), so it
     * doesn't pick up TimeStudy::boot()'s global factory scope automatically — every
     * aggregation here must go through this instead of DB::table('time_studies') directly.
     *
     * Public so SkillMatrixRepository (same report filter set, different aggregation
     * shape) can reuse the exact same scoping logic instead of duplicating it.
     */
    public static function baseQuery(array $filters)
    {
        $query = DB::table('time_studies');

        if (!FactoryContext::isBypassed() && FactoryContext::isResolved()) {
            $query->whereIn('time_studies.factory_id', FactoryContext::ids());
        }

        if (!empty($filters['study_date_from'])) {
            $query->whereDate('time_studies.study_date', '>=', $filters['study_date_from']);
        }
        if (!empty($filters['study_date_to'])) {
            $query->whereDate('time_studies.study_date', '<=', $filters['study_date_to']);
        }
        if (!empty($filters['time_study_types'])) {
            $query->whereIn('time_studies.time_study_type', (array) $filters['time_study_types']);
        }
        if (!empty($filters['operation_ids'])) {
            $query->whereIn('time_studies.operation_id', (array) $filters['operation_ids']);
        }
        if (!empty($filters['product_category_ids'])) {
            $query->whereIn('time_studies.product_category_id', (array) $filters['product_category_ids']);
        }
        if (!empty($filters['machine_type_ids'])) {
            $query->whereIn('time_studies.machine_type_id', (array) $filters['machine_type_ids']);
        }
        if (!empty($filters['employee_ids'])) {
            $query->whereIn('time_studies.employee_id', (array) $filters['employee_ids']);
        }
        if (!empty($filters['product_ids'])) {
            $query->whereIn('time_studies.product_id', (array) $filters['product_ids']);
        }
        if (!empty($filters['factory_ids'])) {
            $query->whereIn('time_studies.factory_id', (array) $filters['factory_ids']);
        }

        return $query;
    }

    public static function getDashboardData(array $filters): array
    {
        $from = !empty($filters['study_date_from'])
            ? Carbon::parse($filters['study_date_from'])->startOfDay()
            : Carbon::now()->subMonths(11)->startOfMonth();
        $to = !empty($filters['study_date_to'])
            ? Carbon::parse($filters['study_date_to'])->endOfDay()
            : Carbon::now()->endOfMonth();

        return [
            'kpis' => self::getKpis($filters),
            'by_type' => self::getByType($filters),
            'down_time_by_reason' => self::getDownTimeByReason($filters),
            'top_operators' => self::getTopOperators($filters),
            'operator_efficiency' => self::getOperatorEfficiency($filters),
            'operator_versatility' => self::getOperatorVersatility($filters),
            'avg_cycle_by_operation' => self::getAvgCycleByOperation($filters),
            'efficiency_by_operation' => self::getEfficiencyByOperation($filters),
            'efficiency_by_product_category' => self::getEfficiencyByProductCategory($filters),
            'efficiency_by_machine_type' => self::getEfficiencyByMachineType($filters),
            'efficiency_trend' => self::getEfficiencyTrend($filters, $from, $to),
        ];
    }

    private static function getKpis(array $filters): array
    {
        $totals = self::baseQuery($filters)
            ->selectRaw('COUNT(*) as total_studies')
            ->selectRaw('AVG(efficiency_pct) as avg_efficiency_pct')
            ->selectRaw('AVG(avg_cycle_ms) as avg_cycle_time_ms')
            ->selectRaw('SUM(total_productive_ms) as total_productive_ms')
            ->selectRaw('SUM(total_down_time_ms) as total_down_time_ms')
            ->first();

        $productiveMs = (float) ($totals->total_productive_ms ?? 0);
        $downMs = (float) ($totals->total_down_time_ms ?? 0);
        $denominator = $productiveMs + $downMs;

        return [
            'total_studies' => (int) $totals->total_studies,
            'avg_efficiency_pct' => $totals->avg_efficiency_pct !== null ? round((float) $totals->avg_efficiency_pct, 1) : null,
            'avg_cycle_time_sec' => $totals->avg_cycle_time_ms !== null ? round(((float) $totals->avg_cycle_time_ms) / 1000, 2) : null,
            'total_productive_sec' => round($productiveMs / 1000, 2),
            'total_down_time_sec' => round($downMs / 1000, 2),
            'down_time_pct' => $denominator > 0 ? round($downMs / $denominator * 100, 1) : 0,
        ];
    }

    private static function getByType(array $filters): array
    {
        $rows = self::baseQuery($filters)
            ->select('time_study_type as label', DB::raw('COUNT(*) as count'))
            ->groupBy('time_study_type')
            ->orderByDesc('count')
            ->get();

        return $rows->map(function ($row) {
            return [
                'label' => $row->label === 'interview_training' ? 'Interview & Training' : 'Production Floor',
                'count' => (int) $row->count,
            ];
        })->toArray();
    }

    private static function getDownTimeByReason(array $filters): array
    {
        $studyIds = self::baseQuery($filters)->select('time_studies.id');

        return DB::table('time_study_downtimes')
            ->joinSub($studyIds, 'filtered_studies', 'filtered_studies.id', '=', 'time_study_downtimes.time_study_id')
            ->join('time_study_downtime_reasons', 'time_study_downtimes.time_study_downtime_reason_id', '=', 'time_study_downtime_reasons.id')
            ->select('time_study_downtime_reasons.code as label', DB::raw('SUM(time_study_downtimes.duration_ms) as total_ms'))
            ->groupBy('time_study_downtime_reasons.id', 'time_study_downtime_reasons.code')
            ->orderByDesc('total_ms')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'count' => round(((float) $row->total_ms) / 1000, 1)])
            ->toArray();
    }

    private static function getTopOperators(array $filters): array
    {
        return self::baseQuery($filters)
            ->join('employees', 'time_studies.employee_id', '=', 'employees.id')
            ->select(
                DB::raw("COALESCE(NULLIF(employees.full_name, ''), CONCAT(employees.first_name, ' ', employees.last_name)) as label"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('employees.id', 'employees.full_name', 'employees.first_name', 'employees.last_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private static function getAvgCycleByOperation(array $filters): array
    {
        return self::baseQuery($filters)
            ->join('operations', 'time_studies.operation_id', '=', 'operations.id')
            ->select('operations.code as label', DB::raw('AVG(time_studies.avg_cycle_ms) as avg_ms'))
            ->whereNotNull('time_studies.avg_cycle_ms')
            ->groupBy('operations.id', 'operations.code')
            ->orderByDesc('avg_ms')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => round(((float) $row->avg_ms) / 1000, 2)])
            ->toArray();
    }

    /** Bottom 10 operators by average efficiency — flags who may need retraining. */
    private static function getOperatorEfficiency(array $filters): array
    {
        return self::baseQuery($filters)
            ->join('employees', 'time_studies.employee_id', '=', 'employees.id')
            ->select(
                DB::raw("COALESCE(NULLIF(employees.full_name, ''), CONCAT(employees.first_name, ' ', employees.last_name)) as label"),
                DB::raw('AVG(time_studies.efficiency_pct) as avg_pct')
            )
            ->whereNotNull('time_studies.efficiency_pct')
            ->groupBy('employees.id', 'employees.full_name', 'employees.first_name', 'employees.last_name')
            ->orderBy('avg_pct')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => round((float) $row->avg_pct, 1)])
            ->toArray();
    }

    /** Top 10 operators by number of distinct operations studied — flags cross-trained/versatile operators. */
    private static function getOperatorVersatility(array $filters): array
    {
        return self::baseQuery($filters)
            ->join('employees', 'time_studies.employee_id', '=', 'employees.id')
            ->select(
                DB::raw("COALESCE(NULLIF(employees.full_name, ''), CONCAT(employees.first_name, ' ', employees.last_name)) as label"),
                DB::raw('COUNT(DISTINCT time_studies.operation_id) as count')
            )
            ->groupBy('employees.id', 'employees.full_name', 'employees.first_name', 'employees.last_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->count])
            ->toArray();
    }

    /** Bottom 10 operations by average efficiency — flags where the process itself may need review. */
    private static function getEfficiencyByOperation(array $filters): array
    {
        return self::baseQuery($filters)
            ->join('operations', 'time_studies.operation_id', '=', 'operations.id')
            ->select('operations.code as label', DB::raw('AVG(time_studies.efficiency_pct) as avg_pct'))
            ->whereNotNull('time_studies.efficiency_pct')
            ->groupBy('operations.id', 'operations.code')
            ->orderBy('avg_pct')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => round((float) $row->avg_pct, 1)])
            ->toArray();
    }

    /** Average efficiency by product category — flags which styles/categories run inefficiently. */
    private static function getEfficiencyByProductCategory(array $filters): array
    {
        return self::baseQuery($filters)
            ->join('product_categories', 'time_studies.product_category_id', '=', 'product_categories.id')
            ->select('product_categories.code as label', DB::raw('AVG(time_studies.efficiency_pct) as avg_pct'))
            ->whereNotNull('time_studies.efficiency_pct')
            ->groupBy('product_categories.id', 'product_categories.code')
            ->orderBy('avg_pct')
            ->limit(15)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => round((float) $row->avg_pct, 1)])
            ->toArray();
    }

    /** Average efficiency by machine type — flags machine-related bottlenecks. */
    private static function getEfficiencyByMachineType(array $filters): array
    {
        return self::baseQuery($filters)
            ->join('machine_types', 'time_studies.machine_type_id', '=', 'machine_types.id')
            ->select('machine_types.code as label', DB::raw('AVG(time_studies.efficiency_pct) as avg_pct'))
            ->whereNotNull('time_studies.efficiency_pct')
            ->groupBy('machine_types.id', 'machine_types.code')
            ->orderBy('avg_pct')
            ->limit(15)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => round((float) $row->avg_pct, 1)])
            ->toArray();
    }

    private static function getEfficiencyTrend(array $filters, Carbon $from, Carbon $to): array
    {
        $rows = self::baseQuery($filters)
            ->select(
                DB::raw("DATE_FORMAT(study_date, '%Y-%m') as month"),
                DB::raw('AVG(efficiency_pct) as avg_efficiency')
            )
            ->whereBetween('study_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy(DB::raw("DATE_FORMAT(study_date, '%Y-%m')"))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $result = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $result[] = [
                'month' => $key,
                'avg_efficiency' => isset($rows[$key]) && $rows[$key]->avg_efficiency !== null
                    ? round((float) $rows[$key]->avg_efficiency, 1)
                    : null,
            ];
            $cursor->addMonth();
        }

        return $result;
    }
}
