<?php

namespace App\Http\Repositories;

use App\Employee;
use App\Operation;
use App\Product;
use App\ProductOperation;
use App\SkillMatrixCalculationCell;
use App\SkillMatrixCalculationRun;

/**
 * Factory-wide, read-only rollup of the latest saved Skill Matrix Insights
 * run: for every operation routed into any active product currently linked
 * to this factory (regardless of team_plan/production status — unlike Gap
 * Analysis), how many currently-active employees are qualified on it.
 * Surfaces "bus factor" risk — single_point_of_failure (exactly 1 qualified
 * active operator) and zero_coverage (0, despite historical data) are the
 * headline risk tiers.
 *
 * "Qualified" = selected_efficiency >= operation.expected_lower_mid_level_efficiency
 * (same rule as GapAnalysisRepository — see project_gap_analysis_data_model_decisions).
 */
class SkillDepthRepository
{
    private const RISK_ORDER = [
        'no_data' => 0,
        'zero_coverage' => 1,
        'single_point_of_failure' => 2,
        'thin_bench' => 3,
        'healthy' => 4,
    ];

    public static function getReport(array $filters = []): array
    {
        // Product::/Employee:: (not DB::table()) so ScopedToFactories/ScopedToFactory
        // auto-filter to the current factory scope — the load-bearing detail here.
        $productIds = Product::where('is_active', true)->pluck('id');

        $operationIds = ProductOperation::whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->pluck('operation_id')
            ->unique()
            ->values();

        $operations = Operation::whereIn('id', $operationIds)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        if ($operations->isEmpty()) {
            return ['kpis' => self::emptyKpis(), 'operations' => [], 'run' => null];
        }

        $run = SkillMatrixCalculationRepository::findRunForScope();
        $cellsByOperation = $run
            ? SkillMatrixCalculationCell::where('calculation_run_id', $run->id)
                ->whereIn('operation_id', $operations->pluck('id'))
                ->get(['employee_id', 'operation_id', 'selected_efficiency'])
                ->groupBy('operation_id')
            : collect();

        $activeEmployeeIds = Employee::where('employee_status', 'Active')->pluck('id');
        $activeEmployeeIdSet = $activeEmployeeIds->flip();

        $neededEmployeeIds = $cellsByOperation->flatten(1)
            ->pluck('employee_id')
            ->unique()
            ->intersect($activeEmployeeIds);
        $employeesById = Employee::whereIn('id', $neededEmployeeIds)
            ->with('team:id,name')
            ->get()
            ->keyBy('id');

        $tally = ['no_data' => 0, 'zero_coverage' => 0, 'single_point_of_failure' => 0, 'thin_bench' => 0, 'healthy' => 0];
        $rows = [];

        foreach ($operations as $operation) {
            $opCells = $cellsByOperation->get($operation->id, collect());
            $hasAnyHistoricalData = $opCells->isNotEmpty();

            $lowerMid = (float) $operation->expected_lower_mid_level_efficiency;
            $topLevel = (float) $operation->expected_top_level_efficiency;

            $activeCells = $opCells->filter(fn ($c) => $activeEmployeeIdSet->has($c->employee_id));
            $qualifiedCells = $activeCells->filter(fn ($c) => (float) $c->selected_efficiency >= $lowerMid);
            $topBandCount = $qualifiedCells->filter(fn ($c) => (float) $c->selected_efficiency >= $topLevel)->count();

            $risk = self::classifyOperation($qualifiedCells->count(), $hasAnyHistoricalData);
            $tally[$risk]++;

            $rows[] = [
                'operation_id' => $operation->id,
                'code' => $operation->code,
                'description' => $operation->description,
                'smv' => (float) $operation->smv,
                'expected_lower_mid_level_efficiency' => $lowerMid,
                'expected_top_level_efficiency' => $topLevel,
                'qualified_active_count' => $qualifiedCells->count(),
                'top_band_count' => $topBandCount,
                'has_any_historical_data' => $hasAnyHistoricalData,
                'risk' => $risk,
                'qualified_employees' => $qualifiedCells
                    ->sortByDesc('selected_efficiency')
                    ->map(fn ($c) => self::mapCandidate($employeesById->get($c->employee_id), $c))
                    ->filter()
                    ->values()
                    ->all(),
            ];
        }

        usort($rows, fn ($a, $b) => self::RISK_ORDER[$a['risk']] <=> self::RISK_ORDER[$b['risk']]
            ?: $a['qualified_active_count'] <=> $b['qualified_active_count']
            ?: strcmp($a['code'] ?? '', $b['code'] ?? ''));

        $known = count($rows) - $tally['no_data'];
        $safeBench = $tally['thin_bench'] + $tally['healthy'];

        return [
            'kpis' => [
                'total_operations' => count($rows),
                'no_data_count' => $tally['no_data'],
                'zero_coverage_count' => $tally['zero_coverage'],
                'single_point_of_failure_count' => $tally['single_point_of_failure'],
                'thin_bench_count' => $tally['thin_bench'],
                'healthy_count' => $tally['healthy'],
                'at_risk_count' => $tally['zero_coverage'] + $tally['single_point_of_failure'],
                'coverage_pct' => $known > 0 ? round($safeBench / $known * 100, 1) : 0.0,
            ],
            'operations' => $rows,
            'run' => self::mapRunMeta($run),
        ];
    }

    /** Precedence: no_data > zero_coverage > single_point_of_failure > thin_bench > healthy. */
    public static function classifyOperation(int $qualifiedActiveCount, bool $hasAnyHistoricalData): string
    {
        if (!$hasAnyHistoricalData) {
            return 'no_data';
        }
        if ($qualifiedActiveCount === 0) {
            return 'zero_coverage';
        }
        if ($qualifiedActiveCount === 1) {
            return 'single_point_of_failure';
        }
        if ($qualifiedActiveCount === 2) {
            return 'thin_bench';
        }

        return 'healthy';
    }

    private static function emptyKpis(): array
    {
        return [
            'total_operations' => 0,
            'no_data_count' => 0,
            'zero_coverage_count' => 0,
            'single_point_of_failure_count' => 0,
            'thin_bench_count' => 0,
            'healthy_count' => 0,
            'at_risk_count' => 0,
            'coverage_pct' => 0.0,
        ];
    }

    private static function mapCandidate(?Employee $employee, SkillMatrixCalculationCell $cell): ?array
    {
        if (!$employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'employee_no' => $employee->employee_no,
            'name' => $employee->full_name ?: trim($employee->first_name . ' ' . $employee->last_name),
            'selected_efficiency' => (float) $cell->selected_efficiency,
            'team_name' => optional($employee->team)->name,
        ];
    }

    private static function mapRunMeta(?SkillMatrixCalculationRun $run): ?array
    {
        if (!$run) {
            return null;
        }

        return [
            'id' => $run->id,
            'study_count_total' => $run->study_count_total,
            'cell_count' => $run->cell_count,
            'calculated_at' => optional($run->calculated_at)->format('Y-m-d H:i:s'),
            'calculated_by' => $run->calculatedBy ? ['id' => $run->calculatedBy->id, 'name' => $run->calculatedBy->name] : null,
        ];
    }
}
