<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-operation efficiency thresholds used to color the Skill Matrix's cells
// (green >= top level, blue >= upper-mid, yellow >= lower-mid, else red).
return new class extends Migration
{
    public function up()
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->decimal('expected_top_level_efficiency', 6, 2)->default(80)->after('smv');
            $table->decimal('expected_upper_mid_level_efficiency', 6, 2)->default(60)->after('expected_top_level_efficiency');
            $table->decimal('expected_lower_mid_level_efficiency', 6, 2)->default(50)->after('expected_upper_mid_level_efficiency');
        });
    }

    public function down()
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn([
                'expected_top_level_efficiency',
                'expected_upper_mid_level_efficiency',
                'expected_lower_mid_level_efficiency',
            ]);
        });
    }
};
