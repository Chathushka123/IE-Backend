<?php

namespace App\Http\Repositories;

use App\Employee;
use App\Operation;
use App\TimeStudy;
use Illuminate\Support\Facades\DB;

/**
 * Operator × Operation efficiency matrix for the Skill Matrix screen.
 * Reuses TimeStudyDashboardRepository::baseQuery() so it's scoped by the exact
 * same factory context + report filters as the Time Study Reports dashboard.
 */
class SkillMatrixRepository
{
    public static function getMatrix(array $filters): array
    {
        $rows = TimeStudyDashboardRepository::baseQuery($filters)
            ->join('employees', 'time_studies.employee_id', '=', 'employees.id')
            ->join('operations', 'time_studies.operation_id', '=', 'operations.id')
            ->select(
                'employees.id as employee_id',
                'employees.employee_no',
                DB::raw("COALESCE(NULLIF(employees.full_name, ''), CONCAT(employees.first_name, ' ', employees.last_name)) as employee_name"),
                'operations.id as operation_id',
                'operations.code as operation_code',
                'operations.description as operation_description',
                'operations.expected_top_level_efficiency',
                'operations.expected_upper_mid_level_efficiency',
                'operations.expected_lower_mid_level_efficiency',
                DB::raw('AVG(time_studies.efficiency_pct) as avg_efficiency'),
                DB::raw('COUNT(*) as study_count')
            )
            ->whereNotNull('time_studies.efficiency_pct')
            ->groupBy(
                'employees.id',
                'employees.employee_no',
                'employees.full_name',
                'employees.first_name',
                'employees.last_name',
                'operations.id',
                'operations.code',
                'operations.description',
                'operations.expected_top_level_efficiency',
                'operations.expected_upper_mid_level_efficiency',
                'operations.expected_lower_mid_level_efficiency'
            )
            ->get();

        $operators = [];
        $operations = [];
        $cells = [];

        foreach ($rows as $row) {
            $operators[$row->employee_id] = [
                'id' => (int) $row->employee_id,
                'employee_no' => $row->employee_no,
                'name' => $row->employee_name,
            ];
            $operations[$row->operation_id] = [
                'id' => (int) $row->operation_id,
                'code' => $row->operation_code,
                'description' => $row->operation_description,
                'expected_top_level_efficiency' => (float) $row->expected_top_level_efficiency,
                'expected_upper_mid_level_efficiency' => (float) $row->expected_upper_mid_level_efficiency,
                'expected_lower_mid_level_efficiency' => (float) $row->expected_lower_mid_level_efficiency,
            ];
            $cells[] = [
                'employee_id' => (int) $row->employee_id,
                'operation_id' => (int) $row->operation_id,
                'avg_efficiency' => round((float) $row->avg_efficiency, 1),
                'study_count' => (int) $row->study_count,
            ];
        }

        $operators = array_values($operators);
        $operations = array_values($operations);

        usort($operators, fn ($a, $b) => strcmp($a['employee_no'] ?? '', $b['employee_no'] ?? ''));
        usort($operations, fn ($a, $b) => strcmp($a['code'] ?? $a['description'], $b['code'] ?? $b['description']));

        return [
            'operators' => $operators,
            'operations' => $operations,
            'cells' => $cells,
        ];
    }

    /**
     * Every study behind one operator×operation cell — same scoping (whereNotNull
     * efficiency_pct, factory context, report filters) as getMatrix() so the
     * avg_efficiency/study_count shown here always reconciles with the cell's value.
     */
    public static function getCellDetail(array $filters): array
    {
        $employeeId = (int) ($filters['employee_id'] ?? 0);
        $operationId = (int) ($filters['operation_id'] ?? 0);

        $studyIds = TimeStudyDashboardRepository::baseQuery($filters)
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
            'employee' => $employee ? [
                'id' => $employee->id,
                'employee_no' => $employee->employee_no,
                'name' => $employee->full_name ?: trim($employee->first_name . ' ' . $employee->last_name),
            ] : null,
            'operation' => $operation ? [
                'id' => $operation->id,
                'code' => $operation->code,
                'description' => $operation->description,
                'expected_top_level_efficiency' => (float) $operation->expected_top_level_efficiency,
                'expected_upper_mid_level_efficiency' => (float) $operation->expected_upper_mid_level_efficiency,
                'expected_lower_mid_level_efficiency' => (float) $operation->expected_lower_mid_level_efficiency,
            ] : null,
            'avg_efficiency' => $studies->count() > 0 ? round((float) $studies->avg('efficiency_pct'), 1) : null,
            'study_count' => $studies->count(),
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
}
