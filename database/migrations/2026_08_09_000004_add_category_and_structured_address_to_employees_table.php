<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// All new columns are nullable — mandatory-ness for the address parts (except postal_code)
// and employee_category is enforced on the frontend only, matching how department_id was
// added to sections/designations. The old free-text `address` column is left in place
// (unused going forward, not dropped) so no historical data is lost.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('employee_category', 20)->nullable()->after('employee_status');
            $table->string('street_name')->nullable()->after('address');
            $table->string('house_no', 50)->nullable()->after('street_name');
            $table->string('address_line')->nullable()->after('house_no');
            $table->string('city_or_province')->nullable()->after('address_line');
            $table->string('postal_code', 20)->nullable()->after('city_or_province');
            $table->unsignedBigInteger('country_id')->nullable()->after('postal_code');

            $table->foreign('country_id', 'fk_employees_country')->references('id')->on('countries');
            $table->index('country_id', 'idx_employees_country');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign('fk_employees_country');
            $table->dropColumn([
                'employee_category',
                'street_name',
                'house_no',
                'address_line',
                'city_or_province',
                'postal_code',
                'country_id',
            ]);
        });
    }
};
