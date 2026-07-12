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
