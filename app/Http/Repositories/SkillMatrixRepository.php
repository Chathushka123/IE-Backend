<?php

namespace App\Http\Repositories;

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
}
