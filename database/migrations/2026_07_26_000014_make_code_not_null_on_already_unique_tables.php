<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// These tables already have a unique index on `code`; this just makes the
// column mandatory. Verified no NULL codes exist in any of them, so no
// backfill is needed. doctrine/dbal isn't installed, so nullability changes
// use raw SQL instead of Schema::table()->change().
return new class extends Migration
{
    private array $tables = [
        'base_operations',
        'countries',
        'customers',
        'departments',
        'destinations',
        'factories',
        'machine_types',
        'operation_grades',
        'operations',
        'soft_skills',
    ];

    public function up()
    {
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY code VARCHAR(50) NOT NULL");
        }
    }

    public function down()
    {
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY code VARCHAR(50) NULL");
        }
    }
};
