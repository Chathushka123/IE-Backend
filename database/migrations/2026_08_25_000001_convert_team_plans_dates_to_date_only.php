<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: the business only plans at day granularity (see team_plans' original
// DATETIME columns from 2026_06_30_090003_extend_line_plans_for_schedule_board,
// which existed for a Gantt board that briefly supported hour-level dragging).
// MySQL truncates the time-of-day on a DATETIME->DATE MODIFY automatically, so
// no separate data-backfill step is needed here.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE team_plans MODIFY planned_start_date DATE NULL');
        DB::statement('ALTER TABLE team_plans MODIFY planned_end_date DATE NULL');
        DB::statement('ALTER TABLE team_plans MODIFY actual_start_date DATE NULL');
        DB::statement('ALTER TABLE team_plans MODIFY actual_end_date DATE NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE team_plans MODIFY planned_start_date DATETIME NULL');
        DB::statement('ALTER TABLE team_plans MODIFY planned_end_date DATETIME NULL');
        DB::statement('ALTER TABLE team_plans MODIFY actual_start_date DATETIME NULL');
        DB::statement('ALTER TABLE team_plans MODIFY actual_end_date DATETIME NULL');
    }
};
