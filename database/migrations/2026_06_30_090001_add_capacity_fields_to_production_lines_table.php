<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('production_lines', function (Blueprint $table) {
            $table->unsignedSmallInteger('working_minutes_per_day')->nullable()->default(480)->after('is_active');
            $table->decimal('target_efficiency_pct', 5, 2)->nullable()->after('working_minutes_per_day');
        });
    }

    public function down()
    {
        Schema::table('production_lines', function (Blueprint $table) {
            $table->dropColumn(['working_minutes_per_day', 'target_efficiency_pct']);
        });
    }
};
