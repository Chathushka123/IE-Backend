<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('epf_no', 50)->nullable()->after('identification_no');
        });

        // MySQL unique indexes allow multiple NULLs, so employees with no EPF No
        // on record yet (unlike employee_no, this is optional) don't collide with
        // each other — only two non-null values in the same factory would.
        Schema::table('employees', function (Blueprint $table) {
            $table->unique(['epf_no', 'factory_id'], 'uk_epf_no_factory');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('uk_epf_no_factory');
            $table->dropColumn('epf_no');
        });
    }
};
