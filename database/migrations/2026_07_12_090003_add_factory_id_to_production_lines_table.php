<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('production_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('factory_id')->nullable()->after('id');
        });

        $defaultFactoryId = DB::table('factories')->where('code', 'DEFAULT')->value('id');
        DB::table('production_lines')->whereNull('factory_id')->update(['factory_id' => $defaultFactoryId]);

        // doctrine/dbal isn't installed, so making the column NOT NULL uses raw SQL
        // instead of Schema::table()->change().
        DB::statement('ALTER TABLE production_lines MODIFY factory_id BIGINT UNSIGNED NOT NULL');

        Schema::table('production_lines', function (Blueprint $table) {
            $table->foreign('factory_id', 'fk_production_lines_factory')->references('id')->on('factories');
            $table->index('factory_id', 'idx_production_lines_factory');
        });
    }

    public function down()
    {
        Schema::table('production_lines', function (Blueprint $table) {
            $table->dropForeign('fk_production_lines_factory');
            $table->dropIndex('idx_production_lines_factory');
            $table->dropColumn('factory_id');
        });
    }
};
