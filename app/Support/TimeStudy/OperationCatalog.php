<?php

namespace App\Support\TimeStudy;

use Illuminate\Support\Facades\DB;

/**
 * Reads the master data a template's scope is matched against. An `operations` row is one base
 * operation on one product category and machine type, so it carries every value a condition can
 * name; the product group comes through the category.
 */
class OperationCatalog
{
    /** Condition field => [table, label expression]. */
    private const REFERENCES = [
        'operation' => ['operations', "COALESCE(NULLIF(code, ''), description)"],
        'base_operation' => ['base_operations', 'name'],
        'base_operation_category' => ['base_operation_categories', 'name'],
        'machine_type' => ['machine_types', 'name'],
        'product_category' => ['product_categories', 'name'],
        'product_group' => ['product_groups', 'name'],
    ];

    /** @return array<int,array<string,mixed>> operation id => attributes + label, active operations only */
    public static function all(): array
    {
        return self::query()->where('o.is_active', true)->get()
            ->mapWithKeys(fn ($row) => [(int) $row->operation => self::present($row)])
            ->all();
    }

    public static function find(int $operationId): ?array
    {
        $row = self::query()->where('o.id', $operationId)->first();

        return $row ? self::present($row) : null;
    }

    public static function referenceTable(string $field): ?string
    {
        return self::REFERENCES[$field][0] ?? null;
    }

    /**
     * Display names for conditions, one query per field used.
     *
     * @param array<int,array{field:string,ref_id:int|string}> $conditions
     * @return array<int,array{field:string,ref_id:int,label:string}>
     */
    public static function labelConditions(array $conditions): array
    {
        $idsByField = [];
        foreach ($conditions as $condition) {
            $idsByField[$condition['field']][] = (int) $condition['ref_id'];
        }

        $names = [];
        foreach ($idsByField as $field => $ids) {
            [$table, $label] = self::REFERENCES[$field];
            $names[$field] = DB::table($table)->whereIn('id', $ids)->selectRaw("id, $label as label")->pluck('label', 'id')->all();
        }

        return array_map(fn ($c) => [
            'field' => $c['field'],
            'ref_id' => (int) $c['ref_id'],
            'label' => (string) ($names[$c['field']][(int) $c['ref_id']] ?? 'Removed item'),
        ], $conditions);
    }

    private static function query()
    {
        return DB::table('operations as o')
            ->join('base_operations as bo', 'o.base_operation_id', '=', 'bo.id')
            ->join('product_categories as pc', 'o.product_category_id', '=', 'pc.id')
            ->selectRaw("o.id as operation, o.code, o.description, bo.name as base_operation_name,
                o.base_operation_id as base_operation, bo.base_operation_category_id as base_operation_category,
                o.machine_type_id as machine_type, o.product_category_id as product_category, pc.product_group_id as product_group");
    }

    private static function present($row): array
    {
        $attributes = [];
        foreach (array_keys(self::REFERENCES) as $field) {
            $attributes[$field] = (int) $row->$field;
        }
        $name = trim(($row->code ? $row->code . ' · ' : '') . ($row->description ?: $row->base_operation_name));

        return ['id' => (int) $row->operation, 'label' => $name, 'attributes' => $attributes];
    }
}
