<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A team's code only needs to be unique within its own factory, not globally.
// Verified no NULL codes and no duplicate (factory_id, code) combinations exist
// at the time of this migration.
return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE teams MODIFY code VARCHAR(50) NOT NULL');

        Schema::table('teams', function (Blueprint $table) {
            $table->unique(['factory_id', 'code'], 'uniq_teams_factory_code');
        });
    }

    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique('uniq_teams_factory_code');
        });

        DB::statement('ALTER TABLE teams MODIFY code VARCHAR(50) NULL');
    }
};
