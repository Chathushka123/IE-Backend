<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('factories', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('id');
            $table->unsignedBigInteger('region_id')->nullable()->after('country_id');
        });

        Schema::table('factories', function (Blueprint $table) {
            $table->foreign('country_id', 'fk_factories_country')->references('id')->on('countries');
            $table->foreign('region_id', 'fk_factories_region')->references('id')->on('regions');
            $table->index('country_id', 'idx_factories_country');
            $table->index('region_id', 'idx_factories_region');
        });
    }

    public function down()
    {
        Schema::table('factories', function (Blueprint $table) {
            $table->dropForeign('fk_factories_country');
            $table->dropForeign('fk_factories_region');
            $table->dropIndex('idx_factories_country');
            $table->dropIndex('idx_factories_region');
            $table->dropColumn(['country_id', 'region_id']);
        });
    }
};
