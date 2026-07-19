<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'factories',
        'departments',
        'line_categories',
        'production_lines',
        'designations',
        'employee_categories',
        'product_groups',
        'product_categories',
        'machine_categories',
        'machine_types',
        'skills',
        'operation_grades',
        'operation_categories',
        'operations',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE `{$table}` CHANGE `description` `name` VARCHAR(255) NOT NULL");
            DB::statement("ALTER TABLE `{$table}` RENAME INDEX `uniq_{$table}_description` TO `uniq_{$table}_name`");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            DB::statement("ALTER TABLE `{$table}` RENAME INDEX `uniq_{$table}_name` TO `uniq_{$table}_description`");
            DB::statement("ALTER TABLE `{$table}` CHANGE `name` `description` VARCHAR(255) NOT NULL");
        }
    }
};
