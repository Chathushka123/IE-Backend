<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// code stays nullable here (only uniqueness was requested for employee_categories,
// unlike the other tables in this batch which also require it to be NOT NULL).
// Verified no duplicate codes exist at the time of this migration.
return new class extends Migration
{
    public function up()
    {
        Schema::table('employee_categories', function (Blueprint $table) {
            $table->unique('code', 'uniq_employee_categories_code');
        });
    }

    public function down()
    {
        Schema::table('employee_categories', function (Blueprint $table) {
            $table->dropUnique('uniq_employee_categories_code');
        });
    }
};
