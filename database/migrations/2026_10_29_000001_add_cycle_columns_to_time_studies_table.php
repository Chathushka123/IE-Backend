<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A combined cycle: one operator timed on several operations one after another in a single stopwatch pass.
// It is saved as one ordinary study per operation (so every rule, the skill matrix and the reports work
// unchanged); these two columns only record that the studies belong together and in which order.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_studies', function (Blueprint $table) {
            $table->char('cycle_group_id', 36)->nullable()->after('time_study_plan_item_id');
            $table->unsignedTinyInteger('cycle_position')->nullable()->after('cycle_group_id');
            $table->index('cycle_group_id', 'idx_time_studies_cycle_group');
        });
    }

    public function down(): void
    {
        Schema::table('time_studies', function (Blueprint $table) {
            $table->dropIndex('idx_time_studies_cycle_group');
            $table->dropColumn(['cycle_group_id', 'cycle_position']);
        });
    }
};
