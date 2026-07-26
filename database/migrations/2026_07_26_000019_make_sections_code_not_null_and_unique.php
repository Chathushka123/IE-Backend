<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Verified no NULL or duplicate codes exist in sections at the time of this migration.
return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE sections MODIFY code VARCHAR(50) NOT NULL');

        Schema::table('sections', function (Blueprint $table) {
            $table->unique('code', 'uniq_sections_code');
        });
    }

    public function down()
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique('uniq_sections_code');
        });

        DB::statement('ALTER TABLE sections MODIFY code VARCHAR(50) NULL');
    }
};
