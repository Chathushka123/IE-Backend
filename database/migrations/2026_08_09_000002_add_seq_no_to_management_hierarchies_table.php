<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Backfills existing rows by `id` order before enforcing NOT NULL, since a fresh
// mandatory column can't ship with existing data left null. MODIFY COLUMN is raw SQL
// because doctrine/dbal isn't installed (see 2026_08_09_000001's ADR) — Schema::table()
// itself is fine for the initial nullable add and for down()'s dropColumn.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('management_hierarchies', function (Blueprint $table) {
            $table->unsignedInteger('seq_no')->nullable()->after('code');
        });

        DB::statement('UPDATE `management_hierarchies` SET `seq_no` = `id` WHERE `seq_no` IS NULL');

        DB::statement('ALTER TABLE `management_hierarchies` MODIFY COLUMN `seq_no` INT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        Schema::table('management_hierarchies', function (Blueprint $table) {
            $table->dropColumn('seq_no');
        });
    }
};
