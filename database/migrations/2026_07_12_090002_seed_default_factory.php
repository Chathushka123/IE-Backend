<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Seeds one "default" factory so the NOT NULL factory_id columns added on
// production_lines/employees in the following migrations have somewhere to
// point existing rows at during backfill.
return new class extends Migration
{
    public function up()
    {
        DB::table('factories')->insert([
            // NOTE: intentionally still 'description', not 'name' — at this point in
            // migration history (2026-07-12) the factories table has not yet been
            // renamed by 2026_07_19_000000_rename_description_to_name_in_ie_module_tables.
            // The row's column is renamed along with everyone else's when that later
            // migration runs. Renaming this to 'name' breaks migrate:fresh / RefreshDatabase
            // with "Unknown column 'name' in 'field list'".
            'description' => 'Default Factory',
            'code' => 'DEFAULT',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('factories')->where('code', 'DEFAULT')->delete();
    }
};
