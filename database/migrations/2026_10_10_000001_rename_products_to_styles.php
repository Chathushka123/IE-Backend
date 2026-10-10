<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: doctrine/dbal is not installed, so Schema::rename()/renameColumn() are unavailable —
// raw SQL is used throughout, mirroring 2026_08_08_000001_rename_line_plans_table_to_team_plans.
//
// Business terminology change: "Product" -> "Style" (product master — product_categories /
// product_groups — is intentionally untouched, so product_category_id keeps its name).
//   products -> styles, product_operations -> style_operations,
//   product_milestones -> style_milestones, factory_product -> factory_style,
//   product_id -> style_id everywhere, plus every index/FK name that said "product(s)".
// The rename map below is applied forwards in up() and inverted in down(), so both
// directions stay in lockstep.
return new class extends Migration
{
    /** [old table => new table] */
    private const TABLES = [
        'products' => 'styles',
        'product_operations' => 'style_operations',
        'product_milestones' => 'style_milestones',
        'factory_product' => 'factory_style',
    ];

    /** Tables (named as after the rename) whose product_id column becomes style_id. */
    private const STYLE_ID_TABLES = [
        'style_operations' => 'NOT NULL',
        'style_milestones' => 'NOT NULL',
        'factory_style' => 'NOT NULL',
        'team_plans' => 'NULL',
        'time_studies' => 'NULL',
    ];

    /**
     * Foreign keys, keyed by table name *before* the rename.
     * [table => [[old name, new name, column (old), ref table (old), on delete], ...]]
     */
    private const FOREIGN_KEYS = [
        'products' => [
            ['fk_products_created_by', 'fk_styles_created_by', 'created_by_id', 'users', null],
            ['fk_products_updated_by', 'fk_styles_updated_by', 'updated_by_id', 'users', null],
            ['fk_products_customer', 'fk_styles_customer', 'customer_id', 'customers', null],
            ['fk_products_season', 'fk_styles_season', 'season_id', 'seasons', null],
            ['fk_products_product_category', 'fk_styles_product_category', 'product_category_id', 'product_categories', null],
        ],
        'product_operations' => [
            ['fk_product_operations_product', 'fk_style_operations_style', 'product_id', 'products', null],
            ['fk_product_operations_operation', 'fk_style_operations_operation', 'operation_id', 'operations', null],
            ['fk_product_operations_created_by', 'fk_style_operations_created_by', 'created_by_id', 'users', null],
            ['fk_product_operations_updated_by', 'fk_style_operations_updated_by', 'updated_by_id', 'users', null],
        ],
        'product_milestones' => [
            ['fk_product_milestones_product', 'fk_style_milestones_style', 'product_id', 'products', null],
            ['fk_product_milestones_created_by', 'fk_style_milestones_created_by', 'created_by_id', 'users', null],
            ['fk_product_milestones_updated_by', 'fk_style_milestones_updated_by', 'updated_by_id', 'users', null],
        ],
        'factory_product' => [
            ['fk_factory_product_factory', 'fk_factory_style_factory', 'factory_id', 'factories', 'CASCADE'],
            ['fk_factory_product_product', 'fk_factory_style_style', 'product_id', 'products', 'CASCADE'],
        ],
        'team_plans' => [
            ['fk_team_plans_product', 'fk_team_plans_style', 'product_id', 'products', null],
        ],
        'time_studies' => [
            ['fk_time_studies_product', 'fk_time_studies_style', 'product_id', 'products', null],
        ],
    ];

    /**
     * Plain/unique indexes, keyed by table name *before* the rename. Includes the
     * implicit indexes MySQL created under some FK names (those without a covering index).
     */
    private const INDEXES = [
        'products' => [
            'uniq_products_name' => 'uniq_styles_name',
            'uniq_products_style_customer_season' => 'uniq_styles_style_customer_season',
            'idx_products_customer' => 'idx_styles_customer',
            'idx_products_season' => 'idx_styles_season',
            'fk_products_created_by' => 'fk_styles_created_by',
            'fk_products_updated_by' => 'fk_styles_updated_by',
            'fk_products_product_category' => 'fk_styles_product_category',
        ],
        'product_operations' => [
            'uniq_product_operations_product_operation' => 'uniq_style_operations_style_operation',
            'uniq_product_operations_product_sequence' => 'uniq_style_operations_style_sequence',
            'fk_product_operations_operation' => 'fk_style_operations_operation',
            'fk_product_operations_created_by' => 'fk_style_operations_created_by',
            'fk_product_operations_updated_by' => 'fk_style_operations_updated_by',
        ],
        'product_milestones' => [
            'uniq_product_milestones_product' => 'uniq_style_milestones_style',
            'fk_product_milestones_created_by' => 'fk_style_milestones_created_by',
            'fk_product_milestones_updated_by' => 'fk_style_milestones_updated_by',
        ],
        'factory_product' => [
            'uniq_factory_product' => 'uniq_factory_style',
            'fk_factory_product_product' => 'fk_factory_style_style',
        ],
        'team_plans' => [
            'idx_team_plans_product' => 'idx_team_plans_style',
        ],
        'time_studies' => [
            'fk_time_studies_product' => 'fk_time_studies_style',
        ],
    ];

    /** The permission-gated screen; screen_name must match the navigator.php caption. */
    private const SCREEN = [
        'old' => ['screen_name' => 'Products', 'screen_code' => 'ie/products'],
        'new' => ['screen_name' => 'Styles', 'screen_code' => 'ie/styles'],
    ];

    public function up(): void
    {
        $this->apply(true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->apply(false);
    }

    private function apply(bool $forward): void
    {
        $tableName = fn (string $old) => $forward ? (self::TABLES[$old] ?? $old) : $old;
        $current = fn (string $old) => $forward ? $old : (self::TABLES[$old] ?? $old);
        $col = fn (string $c) => $c === 'product_id' ? ($forward ? 'style_id' : 'product_id') : $c;

        // 1. Drop every affected FK (named as it currently exists).
        foreach (self::FOREIGN_KEYS as $table => $fks) {
            foreach ($fks as [$oldName, $newName]) {
                $name = $forward ? $oldName : $newName;
                DB::statement("ALTER TABLE `{$current($table)}` DROP FOREIGN KEY `{$name}`");
            }
        }

        // 2. Rename tables.
        foreach (self::TABLES as $old => $new) {
            [$from, $to] = $forward ? [$old, $new] : [$new, $old];
            DB::statement("RENAME TABLE `{$from}` TO `{$to}`");
        }

        // 3. Rename product_id <-> style_id (CHANGE keeps the original type/nullability explicit).
        foreach (self::STYLE_ID_TABLES as $newTable => $nullability) {
            $table = $forward ? $newTable : (array_search($newTable, self::TABLES, true) ?: $newTable);
            [$from, $to] = $forward ? ['product_id', 'style_id'] : ['style_id', 'product_id'];
            DB::statement("ALTER TABLE `{$table}` CHANGE `{$from}` `{$to}` BIGINT UNSIGNED {$nullability}");
        }

        // 4. Rename indexes.
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $oldName => $newName) {
                [$from, $to] = $forward ? [$oldName, $newName] : [$newName, $oldName];
                DB::statement("ALTER TABLE `{$tableName($table)}` RENAME INDEX `{$from}` TO `{$to}`");
            }
        }

        // 5. Re-add FKs under their new (or restored) names.
        foreach (self::FOREIGN_KEYS as $table => $fks) {
            foreach ($fks as [$oldName, $newName, $column, $refTable, $onDelete]) {
                $name = $forward ? $newName : $oldName;
                $onDeleteSql = $onDelete ? " ON DELETE {$onDelete}" : '';
                DB::statement(
                    "ALTER TABLE `{$tableName($table)}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$col($column)}`) "
                    . "REFERENCES `{$tableName($refTable)}` (`id`){$onDeleteSql}"
                );
            }
        }

        // 6. Keep the permission screen in sync with the navigator caption/path.
        [$from, $to] = $forward ? [self::SCREEN['old'], self::SCREEN['new']] : [self::SCREEN['new'], self::SCREEN['old']];
        DB::table('screens')->where('screen_code', $from['screen_code'])->update($to);
    }
};
