<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Laps can be kept on record but left out of the cycle-time stats (avg/fastest/slowest) and
// efficiency — typically the first (warm-up) and last (incomplete) laps. The study's stored
// aggregates are computed from non-excluded laps only; existing laps default to included.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_study_laps', function (Blueprint $table) {
            $table->boolean('is_excluded')->default(false)->after('net_ms');
        });
    }

    public function down(): void
    {
        Schema::table('time_study_laps', function (Blueprint $table) {
            $table->dropColumn('is_excluded');
        });
    }
};
