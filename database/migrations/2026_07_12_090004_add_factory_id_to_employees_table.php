<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('factory_id')->nullable()->after('id');
        });

        $defaultFactoryId = DB::table('factories')->where('code', 'DEFAULT')->value('id');
        DB::table('employees')->whereNull('factory_id')->update(['factory_id' => $defaultFactoryId]);

        // doctrine/dbal isn't installed, so making the column NOT NULL uses raw SQL
        // instead of Schema::table()->change().
        DB::statement('ALTER TABLE employees MODIFY factory_id BIGINT UNSIGNED NOT NULL');

        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('factory_id', 'fk_employees_factory')->references('id')->on('factories');
            $table->index('factory_id', 'idx_employees_factory');
        });
    }

    public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign('fk_employees_factory');
            $table->dropIndex('idx_employees_factory');
            $table->dropColumn('factory_id');
        });
    }
};
