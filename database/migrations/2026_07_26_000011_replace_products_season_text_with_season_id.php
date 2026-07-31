<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Products previously had a free-text `season` column. Now that Season is a
// proper (customer-scoped) master-data table, replace it with a season_id FK.
// Only 1 of 62 existing products had a non-empty season value at the time of
// this migration, so the text is simply dropped rather than migrated.
return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('season_id')->nullable()->after('customer_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('season_id', 'fk_products_season')->references('id')->on('seasons');
            $table->index('season_id', 'idx_products_season');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('season');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('season', 50)->nullable()->after('customer_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign('fk_products_season');
            $table->dropIndex('idx_products_season');
            $table->dropColumn('season_id');
        });
    }
};
