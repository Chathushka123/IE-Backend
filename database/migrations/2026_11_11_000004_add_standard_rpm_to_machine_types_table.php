<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR: nullable — existing machine types have no recorded standard RPM and it is not needed to
// create one. Whole RPM, not decimal: machine speeds are quoted as whole numbers (e.g. 5000).
return new class extends Migration
{
    public function up()
    {
        Schema::table('machine_types', function (Blueprint $table) {
            $table->unsignedInteger('standard_rpm')->nullable()->after('machine_category_id');
        });
    }

    public function down()
    {
        Schema::table('machine_types', function (Blueprint $table) {
            $table->dropColumn('standard_rpm');
        });
    }
};
