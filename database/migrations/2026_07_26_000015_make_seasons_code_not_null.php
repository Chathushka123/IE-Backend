<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE seasons MODIFY code VARCHAR(50) NOT NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE seasons MODIFY code VARCHAR(50) NULL');
    }
};
