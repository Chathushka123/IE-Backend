<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: employee_no becomes server-generated ("EMP-" + id zero-padded to 5 digits) instead
// of user input — see Employee::boot()'s `created` hook. The column is made nullable so the
// initial INSERT (before the auto-increment id is known) can succeed; the hook immediately
// backfills it via a quiet save, so in practice every row still ends up with a value.
// doctrine/dbal is not installed (see 2026_08_07_000002), so raw SQL is used instead of
// Schema::table()->employee_no->nullable()->change().
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `employees` MODIFY `employee_no` VARCHAR(50) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `employees` MODIFY `employee_no` VARCHAR(50) NOT NULL');
    }
};
