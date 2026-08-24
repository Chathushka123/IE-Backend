<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rewrites every existing employee's employee_no to the new server-generated format
 * ("EMP-" + id, zero-padded to 5 digits — see Employee::boot()'s `created` hook,
 * 2026_08_25_000001), so pre-existing rows match what every newly-created employee gets
 * from now on. Pre-migration values are snapshotted into a backup table first so down()
 * can restore them exactly, since the old values (freely user-entered) can't be
 * regenerated from anything else once overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_no_migration_backup', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->primary();
            $table->string('old_employee_no', 50)->nullable();
        });

        DB::statement('
            INSERT INTO `employee_no_migration_backup` (`employee_id`, `old_employee_no`)
            SELECT `id`, `employee_no` FROM `employees`
        ');

        DB::statement("UPDATE `employees` SET `employee_no` = CONCAT('EMP-', LPAD(`id`, 5, '0'))");
    }

    public function down(): void
    {
        DB::statement('
            UPDATE `employees` e
            JOIN `employee_no_migration_backup` b ON b.`employee_id` = e.`id`
            SET e.`employee_no` = b.`old_employee_no`
        ');

        Schema::dropIfExists('employee_no_migration_backup');
    }
};
