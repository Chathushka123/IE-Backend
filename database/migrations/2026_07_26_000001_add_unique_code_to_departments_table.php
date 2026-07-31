<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUniqueCodeToDepartmentsTable extends Migration
{
    public function up()
    {
        // Existing envs may already have duplicate codes predating the unique
        // validators (e.g. seeded/imported before they were added). Resolve
        // those first, otherwise the ALTER TABLE below fails on prod data.
        $duplicateCodes = DB::table('departments')
            ->select('code')
            ->whereNotNull('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('code');

        foreach ($duplicateCodes as $code) {
            $rows = DB::table('departments')
                ->where('code', $code)
                ->orderBy('id')
                ->get(['id']);

            // Keep the oldest row's code untouched; suffix the rest with their id.
            foreach ($rows->slice(1) as $row) {
                DB::table('departments')
                    ->where('id', $row->id)
                    ->update(['code' => substr($code, 0, 44) . '-' . $row->id]);
            }
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->unique('code', 'uniq_departments_code_x');
        });
    }

    public function down()
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('uniq_departments_code_x');
        });
    }
}
