<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ADR: a category's group is now derived through its family (category -> family -> group), so
// product_categories.product_group_id is dropped instead of kept alongside — two columns could
// disagree. Existing data is preserved by creating one default family per group (same name and
// code) and pointing that group's categories at it; rename or split those families afterwards.
return new class extends Migration
{
    public function up()
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('product_family_id')->nullable()->after('code');
        });

        DB::statement(
            'INSERT INTO product_families (name, code, product_group_id, is_active, created_by_id, updated_by_id)
             SELECT name, code, id, is_active, created_by_id, updated_by_id FROM product_groups'
        );

        DB::statement(
            'UPDATE product_categories pc
             JOIN product_families pf ON pf.product_group_id = pc.product_group_id
             SET pc.product_family_id = pf.id'
        );

        DB::statement('ALTER TABLE product_categories MODIFY product_family_id BIGINT UNSIGNED NOT NULL');

        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreign('product_family_id', 'fk_product_categories_product_family')->references('id')->on('product_families');
            $table->dropForeign('fk_product_categories_product_group');
            $table->dropColumn('product_group_id');
        });
    }

    public function down()
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('product_group_id')->nullable()->after('code');
        });

        DB::statement(
            'UPDATE product_categories pc
             JOIN product_families pf ON pf.id = pc.product_family_id
             SET pc.product_group_id = pf.product_group_id'
        );

        DB::statement('ALTER TABLE product_categories MODIFY product_group_id BIGINT UNSIGNED NOT NULL');

        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreign('product_group_id', 'fk_product_categories_product_group')->references('id')->on('product_groups');
            $table->dropForeign('fk_product_categories_product_family');
            $table->dropColumn('product_family_id');
        });
    }
};
