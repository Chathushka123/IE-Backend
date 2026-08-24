<?php

namespace App\Http\Repositories;

use App\Employee;
use App\Exceptions\GeneralException;
use App\Operation;
use App\ProductOperation;
use App\SkillMatrixCalculationCell;
use App\SkillMatrixCalculationRun;
use App\TeamPlan;

/**
 * Live-computed (not saved) Team-Plan x Operation gap matrix: compares the
 * operator headcount a plan's routing requires against who's actually
 * qualified, per the latest Skill Matrix Insights run for the current
 * factory scope. Cheap enough to recompute on every request — team_plans,
 * product_operations and skill_matrix_calculation_cells are all small,
 * already-aggregated tables, unlike the raw time_studies scans
 * SkillMatrixCalculationRepository::recalculate() has to do.
 *
 * "Qualified" = selected_efficiency >= operation.expected_lower_mid_level_efficiency.
 * expected_top_level_efficiency is a separate "quality gap" signal (staffed
 * at the minimum bar, nobody clears the top band). Operation = skill 1:1 —
 * no operation_skill/soft_skills involved.
 */
class GapAnalysisRepository
{
    public static function getMatrix(array $filters): array
    {
        $query = TeamPlan::query()
            ->whereHas('team') // Team's own ScopedToFactory global scope does the factory filtering
            ->whereNotNull('product_id')
            ->with(['team:id,name,code,target_efficiency_pct', 'product:id,name,style_code']);

        $statuses = $filters['statuses'] ?? ['planned', 'in_progress'];
        $query->whereIn('status', $statuses);

        if (!empty($filters['team_ids'])) {
            $query->whereIn('team_id', $filters['team_ids']);
        }
        if (!empty($filters['product_ids'])) {
            $query->whereIn('product_id', $filters['product_ids']);
        }
        // Overlap filter — same semantics as TeamPlanController::index.
        if (!empty($filters['date_from'])) {
            $query->where(fn ($q) => $q->whereNull('planned_end_date')->orWhere('planned_end_date', '>=', $filters['date_from']));
        }
        if (!empty($filters['date_to'])) {
            $query->where(fn ($q) => $q->whereNull('planned_start_date')->orWhere('planned_start_date', '<=', $filters['date_to']));
        }

        $teamPlans = $query->orderBy('team_id')->orderBy('sequence_no')->get();

        if ($teamPlans->isEmpty()) {
            return ['kpis' => self::emptyKpis(), 'rows' => [], 'columns' => [], 'cells' => [], 'run' => null];
        }

        $productIds = $teamPlans->pluck('product_id')->unique()->values();
        $productOperations = ProductOperation::with('operation')
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->orderBy('sequence_no')
            ->get()
            ->groupBy('product_id');

        $operations = $productOperations->flatten(1)->pluck('operation')->filter()->unique('id')->values();
        $allOperationIds = $operations->pluck('id');

        $teamIds = $teamPlans->pluck('team_id')->unique()->values();
        $employeesByTeam = Employee::where('employee_status', 'Active')
            ->whereIn('team_id', $teamIds)
            ->get(['id', 'team_id'])
            ->groupBy('team_id');

        $run = SkillMatrixCalculationRepository::findRunForScope();
        $cellsByOperation = $run
            ? SkillMatrixCalculationCell::where('calculation_run_id', $run->id)
                ->whereIn('operation_id', $allOperationIds)
                ->get(['employee_id', 'operation_id', 'selected_efficiency'])
                ->groupBy('operation_id')
            : collect();

        $rows = [];
        $cells = [];
        $tally = ['ok' => 0, 'quality_gap' => 0, 'shortfall' => 0, 'critical' => 0, 'unknown' => 0];
        $atRiskPlanIds = [];

        foreach ($teamPlans as $plan) {
            $rows[] = self::mapRow($plan);
            $teamEmployeeIds = $employeesByTeam->get($plan->team_id, collect())->pluck('id');
            $routing = $productOperations->get($plan->product_id, collect());

            foreach ($routing as $productOperation) {
                $operation = $productOperation->operation;
                if (!$operation) {
                    continue;
                }

                $smv = (float) ($productOperation->smv ?? $operation->smv ?? 0);
                [$required, ] = self::computeRequiredOperators($plan, $operation, $smv);

                $opCells = $cellsByOperation->get($operation->id, collect());
                $teamOpCells = $opCells->whereIn('employee_id', $teamEmployeeIds);
                $lowerMid = (float) $operation->expected_lower_mid_level_efficiency;
                $topLevel = (float) $operation->expected_top_level_efficiency;
                $qualifiedCells = $teamOpCells->filter(fn ($cell) => (float) $cell->selected_efficiency >= $lowerMid);
                $hasAnyTopBand = $qualifiedCells->contains(fn ($cell) => (float) $cell->selected_efficiency >= $topLevel);
                $hasAnyHistoricalData = $opCells->isNotEmpty();

                $status = self::classifyCell($required, $qualifiedCells->count(), $hasAnyTopBand, $hasAnyHistoricalData);

                $tally[$status]++;
                if (in_array($status, ['critical', 'shortfall'], true)) {
                    $atRiskPlanIds[$plan->id] = true;
                }

                $cells[] = [
                    'team_plan_id' => $plan->id,
                    'operation_id' => $operation->id,
                    'required_operators' => $required,
                    'qualified_active_count' => $qualifiedCells->count(),
                    'has_any_top_band' => $hasAnyTopBand,
                    'has_any_historical_data' => $hasAnyHistoricalData,
                    'status' => $status,
                ];
            }
        }

        $known = $tally['ok'] + $tally['quality_gap'] + $tally['shortfall'] + $tally['critical'];

        return [
            'kpis' => [
                'total_cells' => count($cells),
                'ok_count' => $tally['ok'],
                'quality_gap_count' => $tally['quality_gap'],
                'shortfall_count' => $tally['shortfall'],
                'critical_count' => $tally['critical'],
                'unknown_count' => $tally['unknown'],
                'readiness_pct' => $known > 0 ? round($tally['ok'] / $known * 100, 1) : 0.0,
                'teams_at_risk' => count($atRiskPlanIds),
            ],
            'rows' => $rows,
            'columns' => $operations->map(fn ($op) => self::mapOperation($op))->values()->all(),
            'cells' => $cells,
            'run' => self::mapRunMeta($run),
        ];
    }

    public static function getCellDetail(int $teamPlanId, int $operationId): array
    {
        $plan = TeamPlan::with(['team', 'product'])->findOrFail($teamPlanId);
        if (!$plan->team) {
            // TeamPlan itself carries no factory_id and isn't auto-scoped —
            // this replaces the scoping ScopedToFactory would otherwise give
            // us for free, since $plan->team resolves null when the plan's
            // team belongs to a factory outside the current scope.
            throw new GeneralException('Team plan not found in the current factory scope.');
        }

        $operation = Operation::findOrFail($operationId);
        $productOperation = ProductOperation::where('product_id', $plan->product_id)
            ->where('operation_id', $operationId)
            ->first();
        $smv = (float) (optional($productOperation)->smv ?? $operation->smv ?? 0);
        [$required, ] = self::computeRequiredOperators($plan, $operation, $smv);

        $run = SkillMatrixCalculationRepository::findRunForScope();
        $cellsForOp = $run
            ? SkillMatrixCalculationCell::where('calculation_run_id', $run->id)
                ->where('operation_id', $operationId)
                ->get(['employee_id', 'selected_efficiency'])
            : collect();
        $cellsByEmployee = $cellsForOp->keyBy('employee_id');

        $teamEmployeeIds = Employee::where('team_id', $plan->team_id)
            ->where('employee_status', 'Active')
            ->pluck('id');

        $lowerMid = (float) $operation->expected_lower_mid_level_efficiency;

        $qualifiedIds = [];
        $trainingGaps = [];
        foreach ($teamEmployeeIds as $empId) {
            $cell = $cellsByEmployee->get($empId);
            if (!$cell) {
                continue; // no historical data at all — not a training candidate, not qualified
            }
            $efficiency = (float) $cell->selected_efficiency;
            if ($efficiency >= $lowerMid) {
                $qualifiedIds[] = $empId;
            } else {
                $trainingGaps[$empId] = round($lowerMid - $efficiency, 2);
            }
        }

        $otherQualifiedIds = $cellsForOp
            ->whereNotIn('employee_id', $teamEmployeeIds)
            ->filter(fn ($cell) => (float) $cell->selected_efficiency >= $lowerMid)
            ->pluck('employee_id');

        $qualifiedList = Employee::whereIn('id', $qualifiedIds)->get()
            ->map(fn ($e) => self::mapCandidate($e, $cellsByEmployee->get($e->id)))
            ->sortByDesc('selected_efficiency')->values()->all();

        $trainingList = Employee::whereIn('id', array_keys($trainingGaps))->get()
            ->map(fn ($e) => self::mapCandidate($e, $cellsByEmployee->get($e->id), $trainingGaps[$e->id]))
            ->sortBy('gap')->values()->all();

        $reassignmentList = Employee::whereIn('id', $otherQualifiedIds)
            ->where('employee_status', 'Active')
            ->with('team:id,name')
            ->get()
            ->map(fn ($e) => self::mapCandidate($e, $cellsByEmployee->get($e->id), null, true))
            ->sortByDesc('selected_efficiency')->values()->all();

        return [
            'team_plan' => self::mapRow($plan),
            'operation' => self::mapOperation($operation),
            'required_operators' => $required,
            'qualified' => $qualifiedList,
            'training_candidates' => $trainingList,
            'reassignment_candidates' => $reassignmentList,
        ];
    }

    /**
     * @return array{0: int|null, 1: string|null} [required_operators, unknown_reason]
     * unknown_reason is null when required_operators is non-null.
     */
    public static function computeRequiredOperators(TeamPlan $plan, Operation $operation, float $smv): array
    {
        if (!$plan->planned_start_date || !$plan->planned_end_date) {
            return [null, 'missing_dates'];
        }
        if (!$plan->planned_quantity || $plan->planned_quantity <= 0) {
            return [null, 'missing_quantity'];
        }
        if ($smv <= 0) {
            return [null, 'missing_smv'];
        }

        $targetEfficiencyPct = (float) ($plan->team->target_efficiency_pct ?? 0);
        if ($targetEfficiencyPct <= 0) {
            return [null, 'missing_target_efficiency'];
        }

        $durationHours = ($plan->planned_end_date->getTimestamp() - $plan->planned_start_date->getTimestamp()) / 3600;
        if ($durationHours <= 0) {
            return [null, 'invalid_duration'];
        }

        // Same shape as TeamPlanRepository::suggestSchedule()'s hourlyCapacity,
        // but per-operator (no operatorCount multiplication) and per-operation
        // SMV (not the product's total routing SMV) — this is per-cell demand.
        $hourlyCapacityPerOperator = (60 * ($targetEfficiencyPct / 100)) / $smv;
        if ($hourlyCapacityPerOperator <= 0) {
            return [null, 'zero_capacity'];
        }

        $required = (int) ceil($plan->planned_quantity / ($hourlyCapacityPerOperator * $durationHours));

        return [$required, null];
    }

    /** Precedence: unknown > critical > shortfall > quality_gap > ok. */
    public static function classifyCell(
        ?int $requiredOperators,
        int $qualifiedActiveCount,
        bool $hasAnyTopBand,
        bool $hasAnyHistoricalData
    ): string {
        if ($requiredOperators === null) {
            return 'unknown';
        }
        if (!$hasAnyHistoricalData) {
            return 'unknown';
        }
        if ($qualifiedActiveCount === 0) {
            return 'critical';
        }
        if ($qualifiedActiveCount < $requiredOperators) {
            return 'shortfall';
        }
        if (!$hasAnyTopBand) {
            return 'quality_gap';
        }

        return 'ok';
    }

    private static function emptyKpis(): array
    {
        return [
            'total_cells' => 0,
            'ok_count' => 0,
            'quality_gap_count' => 0,
            'shortfall_count' => 0,
            'critical_count' => 0,
            'unknown_count' => 0,
            'readiness_pct' => 0.0,
            'teams_at_risk' => 0,
        ];
    }

    private static function mapRow(TeamPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'team_id' => $plan->team_id,
            'team_name' => optional($plan->team)->name,
            'team_code' => optional($plan->team)->code,
            'product_id' => $plan->product_id,
            'product_name' => optional($plan->product)->name,
            'style_code' => optional($plan->product)->style_code,
            'planned_quantity' => $plan->planned_quantity,
            'planned_start_date' => optional($plan->planned_start_date)->format('Y-m-d H:i:s'),
            'planned_end_date' => optional($plan->planned_end_date)->format('Y-m-d H:i:s'),
            'status' => $plan->status,
        ];
    }

    private static function mapOperation(Operation $operation): array
    {
        return [
            'id' => $operation->id,
            'code' => $operation->code,
            'description' => $operation->description,
            'smv' => (float) $operation->smv,
            'expected_top_level_efficiency' => (float) $operation->expected_top_level_efficiency,
            'expected_upper_mid_level_efficiency' => (float) $operation->expected_upper_mid_level_efficiency,
            'expected_lower_mid_level_efficiency' => (float) $operation->expected_lower_mid_level_efficiency,
        ];
    }

    private static function mapCandidate(Employee $employee, ?SkillMatrixCalculationCell $cell, ?float $gap = null, bool $includeTeamName = false): array
    {
        $candidate = [
            'id' => $employee->id,
            'employee_no' => $employee->employee_no,
            'name' => $employee->full_name ?: trim($employee->first_name . ' ' . $employee->last_name),
            'selected_efficiency' => $cell ? (float) $cell->selected_efficiency : null,
        ];

        if ($gap !== null) {
            $candidate['gap'] = $gap;
        }
        if ($includeTeamName) {
            $candidate['team_name'] = optional($employee->team)->name;
        }

        return $candidate;
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
