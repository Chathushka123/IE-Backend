<?php

namespace App\Http\Repositories;

use App\Employee;
use App\Operation;
use App\SkillMatrixCalculationCell;
use App\SkillMatrixCalculationRun;
use App\Support\FactoryContext;
use App\TimeStudy;
use Illuminate\Support\Facades\DB;

/**
 * Precomputed, "latest only" Operator x Operation efficiency matrix for the
 * Skill Matrix Insights screen. Unlike the live SkillMatrixRepository (which
 * always averages efficiency_pct on every page view), this repository saves
 * a rich per-cell stat set (mean/median/mode/min/max/stddev) so:
 *   - the grid is a cheap read instead of a live aggregation every view
 *   - each operation can choose mean/median/mode as its "selected" value
 *   - the data is rich enough to be reused as future ML training data
 *
 * Retention is "latest only" per factory-scope (FactoryContext::ids()) —
 * recalculating under one factory scope must not overwrite another
 * factory-scope's saved matrix, so every read/write here matches on scope
 * via sameScope() (order-independent array comparison) rather than assuming
 * a single global "latest" row.
 */
class SkillMatrixCalculationRepository
{
    /**
     * Fetches raw rows for the current filters/scope, computes descriptive
     * stats per employee x operation cell, and persists them as the new
     * "latest" run for the current factory scope (replacing any prior run
     * under the same scope; other scopes' runs are left untouched).
     */
    public static function recalculate(array $filters, int $calculatedById): SkillMatrixCalculationRun
    {
        $rows = TimeStudyDashboardRepository::baseQuery($filters)
            ->join('operations', 'time_studies.operation_id', '=', 'operations.id')
            ->select(
                'time_studies.employee_id',
                'time_studies.operation_id',
                'time_studies.efficiency_pct',
                'time_studies.study_date',
                'operations.calculation_method',
                'operations.mode_bin_size_pct'
            )
            ->whereNotNull('time_studies.efficiency_pct')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->employee_id . ':' . $row->operation_id;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'employee_id' => (int) $row->employee_id,
                    'operation_id' => (int) $row->operation_id,
                    'calculation_method' => $row->calculation_method,
                    'mode_bin_size_pct' => (int) $row->mode_bin_size_pct,
                    'values' => [],
                    'dates' => [],
                ];
            }
            $grouped[$key]['values'][] = (float) $row->efficiency_pct;
            $grouped[$key]['dates'][] = (string) $row->study_date;
        }

        $now = now();
        $studyCountTotal = 0;
        $cellRows = [];

        foreach ($grouped as $group) {
            $values = $group['values'];
            $count = count($values);
            $studyCountTotal += $count;

            $mean = array_sum($values) / $count;
            $median = self::median($values);
            $mode = self::modeWithBinning($values, $group['mode_bin_size_pct']);
            $stddev = self::sampleStdDev($values);

            $method = $group['calculation_method'];
            if ($method === 'median') {
                $selected = $median;
            } elseif ($method === 'mode') {
                $selected = $mode['used_fallback'] ? $mean : $mode['value'];
            } else {
                $selected = $mean;
            }

            sort($group['dates']);

            $cellRows[] = [
                'employee_id' => $group['employee_id'],
                'operation_id' => $group['operation_id'],
                'study_count' => $count,
                'mean_efficiency' => round($mean, 2),
                'median_efficiency' => round($median, 2),
                'mode_efficiency' => $mode['value'] !== null ? round($mode['value'], 2) : null,
                'mode_bin_size_pct' => $group['mode_bin_size_pct'],
                'mode_used_fallback_to_mean' => $mode['used_fallback'],
                'min_efficiency' => round(min($values), 2),
                'max_efficiency' => round(max($values), 2),
                'stddev_efficiency' => $stddev !== null ? round($stddev, 4) : null,
                'calculation_method_used' => $method,
                'selected_efficiency' => round($selected, 2),
                'first_study_date' => $group['dates'][0] ?? null,
                'last_study_date' => $group['dates'][count($group['dates']) - 1] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $factoryIds = FactoryContext::isBypassed() ? null : FactoryContext::ids();

        return DB::transaction(function () use ($filters, $calculatedById, $factoryIds, $cellRows, $studyCountTotal, $now) {
            // "Latest only" per factory-scope: drop any prior run(s) matching
            // this exact scope (cells cascade-delete via FK); other scopes
            // are untouched.
            SkillMatrixCalculationRun::all()
                ->filter(fn ($run) => self::sameScope($run->factory_ids, $factoryIds))
                ->each(fn ($run) => $run->delete());

            $run = SkillMatrixCalculationRun::create([
                'factory_ids' => $factoryIds,
                'filters' => $filters,
                'study_count_total' => $studyCountTotal,
                'cell_count' => count($cellRows),
                'calculated_by_id' => $calculatedById,
                'calculated_at' => $now,
            ]);

            foreach (array_chunk($cellRows, 500) as $chunk) {
                DB::table('skill_matrix_calculation_cells')->insert(array_map(
                    fn ($cell) => array_merge(['calculation_run_id' => $run->id], $cell),
                    $chunk
                ));
            }

            return $run->fresh();
        });
    }

    /**
     * Reshapes the run matching the current factory scope into the same
     * {operators, operations, cells} shape SkillMatrixRepository::getMatrix()
     * uses (plus a `run` metadata block) — null when no run has been saved
     * yet for this scope.
     */
    public static function getLatest(?array $requestFactoryIds = null): ?array
    {
        $run = self::findRunForScope($requestFactoryIds);

        if (!$run) {
            return null;
        }

        $run->load(['cells.employee', 'cells.operation', 'calculatedBy']);

        $operators = [];
        $operations = [];
        $cells = [];

        foreach ($run->cells as $cell) {
            $employee = $cell->employee;
            $operation = $cell->operation;

            if ($employee && !isset($operators[$employee->id])) {
                $operators[$employee->id] = self::mapEmployee($employee);
            }

            if ($operation && !isset($operations[$operation->id])) {
                $operations[$operation->id] = self::mapOperation($operation);
            }

            $cells[] = self::mapCell($cell);
        }

        $operators = array_values($operators);
        $operations = array_values($operations);

        usort($operators, fn ($a, $b) => strcmp($a['employee_no'] ?? '', $b['employee_no'] ?? ''));
        usort($operations, fn ($a, $b) => strcmp($a['code'] ?? $a['description'], $b['code'] ?? $b['description']));

        return [
            'run' => self::mapRun($run),
            'operators' => $operators,
            'operations' => $operations,
            'cells' => $cells,
        ];
    }

    /**
     * Every study behind one saved cell, reconstructed from the run's own
     * filters via TimeStudyDashboardRepository::baseQuery() (same technique
     * SkillMatrixRepository::getCellDetail() uses) plus the saved cell's
     * full stat row. Null when there's no saved run for this scope, or no
     * saved cell for this employee/operation pair within it.
     */
    public static function getCellDetail(int $employeeId, int $operationId): ?array
    {
        $run = self::findRunForScope();

        if (!$run) {
            return null;
        }

        $cell = SkillMatrixCalculationCell::where('calculation_run_id', $run->id)
            ->where('employee_id', $employeeId)
            ->where('operation_id', $operationId)
            ->first();

        if (!$cell) {
            return null;
        }

        $studyIds = TimeStudyDashboardRepository::baseQuery($run->filters ?? [])
            ->where('time_studies.employee_id', $employeeId)
            ->where('time_studies.operation_id', $operationId)
            ->whereNotNull('time_studies.efficiency_pct')
            ->pluck('time_studies.id');

        $studies = TimeStudy::with(['factory', 'product'])
            ->whereIn('id', $studyIds)
            ->orderByDesc('study_date')
            ->get();

        $employee = Employee::find($employeeId);
        $operation = Operation::find($operationId);

        return [
            'employee' => $employee ? self::mapEmployee($employee) : null,
            'operation' => $operation ? self::mapOperation($operation) : null,
            'cell' => self::mapCell($cell),
            'studies' => $studies->map(function ($study) {
                return [
                    'id' => $study->id,
                    'study_date' => optional($study->study_date)->format('Y-m-d'),
                    'time_study_type' => $study->time_study_type,
                    'smv' => $study->smv,
                    'avg_cycle_ms' => $study->avg_cycle_ms,
                    'total_productive_ms' => $study->total_productive_ms,
                    'total_down_time_ms' => $study->total_down_time_ms,
                    'efficiency_pct' => $study->efficiency_pct,
                    'factory' => optional($study->factory)->name,
                    'product' => optional($study->product)->name,
                    'style_code' => optional($study->product)->style_code,
                ];
            })->values(),
        ];
    }

    /**
     * Median of a plain array of numeric values (sorted middle value; average
     * of the two middles when the count is even). Public + static so it's
     * unit-testable without touching the DB.
     */
    public static function median(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        sort($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
        }

        return (float) $values[$middle];
    }

    /**
     * Buckets values by floor(value / binSizePct) and returns the average of
     * the real values inside the highest-count bucket (not the bucket
     * midpoint). Ties between buckets of equal count are broken
     * deterministically by the lowest bucket index. When the best bucket has
     * at most 1 value, there is no real mode — caller should fall back to
     * the mean (`used_fallback = true`, `value = null`).
     *
     * @return array{value: ?float, used_fallback: bool}
     */
    public static function modeWithBinning(array $values, int $binSizePct): array
    {
        if (empty($values) || $binSizePct < 1) {
            return ['value' => null, 'used_fallback' => true];
        }

        $buckets = [];
        foreach ($values as $value) {
            $bucketIndex = (int) floor($value / $binSizePct);
            $buckets[$bucketIndex][] = $value;
        }

        $maxCount = max(array_map('count', $buckets));

        if ($maxCount <= 1) {
            return ['value' => null, 'used_fallback' => true];
        }

        $candidateIndexes = array_keys(array_filter($buckets, fn ($bucket) => count($bucket) === $maxCount));
        sort($candidateIndexes);
        $winningBucket = $buckets[$candidateIndexes[0]];

        return ['value' => array_sum($winningBucket) / count($winningBucket), 'used_fallback' => false];
    }

    /** Sample standard deviation (n-1); null when there are fewer than 2 values. */
    public static function sampleStdDev(array $values): ?float
    {
        $count = count($values);
        if ($count < 2) {
            return null;
        }

        $mean = array_sum($values) / $count;
        $sumSquaredDiffs = array_sum(array_map(fn ($value) => ($value - $mean) ** 2, $values));

        return sqrt($sumSquaredDiffs / ($count - 1));
    }

    /**
     * Order-independent comparison of a stored run's factory_ids against a
     * scope's ids — both null means "unscoped/bypassed" and counts as a
     * match; a null on only one side never matches a concrete id list.
     */
    private static function sameScope(?array $a, ?array $b): bool
    {
        if ($a === null || $b === null) {
            return $a === null && $b === null;
        }

        sort($a);
        sort($b);

        return $a === $b;
    }

    public static function findRunForScope(?array $requestFactoryIds = null): ?SkillMatrixCalculationRun
    {
        $factoryIds = $requestFactoryIds ?? (FactoryContext::isBypassed() ? null : FactoryContext::ids());

        return SkillMatrixCalculationRun::all()
            ->first(fn ($run) => self::sameScope($run->factory_ids, $factoryIds));
    }

    private static function mapRun(SkillMatrixCalculationRun $run): array
    {
        return [
            'id' => $run->id,
            'factory_ids' => $run->factory_ids,
            'filters' => $run->filters,
            'study_count_total' => $run->study_count_total,
            'cell_count' => $run->cell_count,
            'calculated_at' => optional($run->calculated_at)->format('Y-m-d H:i:s'),
            'calculated_by' => $run->calculatedBy ? [
                'id' => $run->calculatedBy->id,
                'name' => $run->calculatedBy->name,
            ] : null,
        ];
    }

    private static function mapEmployee(Employee $employee): array
    {
        return [
            'id' => (int) $employee->id,
            'employee_no' => $employee->employee_no,
            'name' => $employee->full_name ?: trim($employee->first_name . ' ' . $employee->last_name),
        ];
    }

    private static function mapOperation(Operation $operation): array
    {
        return [
            'id' => (int) $operation->id,
            'code' => $operation->code,
            'description' => $operation->description,
            'expected_top_level_efficiency' => (float) $operation->expected_top_level_efficiency,
            'expected_upper_mid_level_efficiency' => (float) $operation->expected_upper_mid_level_efficiency,
            'expected_lower_mid_level_efficiency' => (float) $operation->expected_lower_mid_level_efficiency,
            'calculation_method' => $operation->calculation_method,
            'mode_bin_size_pct' => (int) $operation->mode_bin_size_pct,
        ];
    }

    private static function mapCell(SkillMatrixCalculationCell $cell): array
    {
        return [
            'employee_id' => (int) $cell->employee_id,
            'operation_id' => (int) $cell->operation_id,
            'study_count' => (int) $cell->study_count,
            'mean_efficiency' => $cell->mean_efficiency !== null ? (float) $cell->mean_efficiency : null,
            'median_efficiency' => $cell->median_efficiency !== null ? (float) $cell->median_efficiency : null,
            'mode_efficiency' => $cell->mode_efficiency !== null ? (float) $cell->mode_efficiency : null,
            'mode_bin_size_pct' => $cell->mode_bin_size_pct !== null ? (int) $cell->mode_bin_size_pct : null,
            'mode_used_fallback_to_mean' => (bool) $cell->mode_used_fallback_to_mean,
            'min_efficiency' => $cell->min_efficiency !== null ? (float) $cell->min_efficiency : null,
            'max_efficiency' => $cell->max_efficiency !== null ? (float) $cell->max_efficiency : null,
            'stddev_efficiency' => $cell->stddev_efficiency !== null ? (float) $cell->stddev_efficiency : null,
            'calculation_method_used' => $cell->calculation_method_used,
            'selected_efficiency' => $cell->selected_efficiency !== null ? (float) $cell->selected_efficiency : null,
            'first_study_date' => optional($cell->first_study_date)->format('Y-m-d'),
            'last_study_date' => optional($cell->last_study_date)->format('Y-m-d'),
        ];
    }
}
