<?php

namespace App\Support\TimeStudy;

/**
 * Checks a study plan (who will be timed on which operation) against the template's layout and scope.
 * Pure: plain arrays in, plain-language problems out. The browser has a TypeScript port, kept equal by
 * the shared cases in tests/Fixtures/StudyPlan/cases.json, so it can warn while the plan is being built;
 * this one decides when it is saved.
 */
class PlanValidator
{
    /**
     * @param array $layout the template's layout (TemplateDefinition::layoutOf)
     * @param array<int,array{operation_id:int,employee_id:int}> $pairs the planned studies that are still wanted (not skipped)
     * @param int[]|null $coverage operation ids the template covers; null when it has no limit
     * @param array{operations?:array<int,string>,operators?:array<int,string>} $names display names by id
     * @param bool $requireMinimums false while a plan is running: a shortfall left by a skipped pair is
     *                              shown as progress instead of blocking the next change
     * @return string[]
     */
    public static function problems(array $layout, array $pairs, ?array $coverage, array $names = [], bool $requireMinimums = true): array
    {
        $problems = [];

        if ($requireMinimums && $pairs === []) {
            return ['Add at least one operator to an operation.'];
        }

        $operatorsByOperation = [];
        $operationsByOperator = [];
        foreach ($pairs as $pair) {
            $operatorsByOperation[$pair['operation_id']][$pair['employee_id']] = true;
            $operationsByOperator[$pair['employee_id']][$pair['operation_id']] = true;
        }
        ksort($operatorsByOperation);
        ksort($operationsByOperator);

        $operationName = fn (int $id) => $names['operations'][$id] ?? "Operation #{$id}";
        $operatorName = fn (int $id) => $names['operators'][$id] ?? "Operator #{$id}";

        if ($coverage !== null) {
            foreach (array_keys($operatorsByOperation) as $operationId) {
                if (!in_array($operationId, $coverage, true)) {
                    $problems[] = "{$operationName($operationId)} is not covered by this template.";
                }
            }
        }

        $mode = $layout['operators_per_operation']['mode'] ?? 'any';
        $min = (int) ($layout['operators_per_operation']['min'] ?? 2);
        foreach ($operatorsByOperation as $operationId => $operators) {
            $count = count($operators);
            if ($mode === 'one' && $count > 1) {
                $problems[] = "{$operationName($operationId)} has {$count} operators, but this template times one operator per operation.";
            }
            if ($mode === 'several' && $requireMinimums && $count < $min) {
                $problems[] = "{$operationName($operationId)} has {$count} " . ($count === 1 ? 'operator' : 'operators') . "; this template needs at least {$min}.";
            }
        }

        if (($layout['operations_per_operator'] ?? 'several') === 'one') {
            foreach ($operationsByOperator as $employeeId => $operations) {
                if (count($operations) > 1) {
                    $problems[] = "{$operatorName($employeeId)} is on " . count($operations) . ' operations, but this template times one operation per operator.';
                }
            }
        }

        return $problems;
    }
}
