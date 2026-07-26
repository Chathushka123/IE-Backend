<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Verified no NULL or duplicate codes exist in designations at the time of this migration.
return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE designations MODIFY code VARCHAR(50) NOT NULL');

        Schema::table('designations', function (Blueprint $table) {
            $table->unique('code', 'uniq_designations_code');
        });
    }

    public function down()
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->dropUnique('uniq_designations_code');
        });

        DB::statement('ALTER TABLE designations MODIFY code VARCHAR(50) NULL');
    }
};
