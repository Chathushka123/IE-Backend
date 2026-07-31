<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// style_code, product_category_id, customer_id, season_id are now mandatory
// on Product, and together form the table's unique key (replacing the old
// standalone unique on style_code). Existing rows predate customer_id/season_id
// being required, so they're backfilled to Customer #3 (Decathlon) / Season #4
// (Summer 27, which belongs to Decathlon) before the NOT NULL constraints are
// applied — confirmed against dev data there are no resulting duplicate
// (style_code, product_category_id, customer_id, season_id) combinations.
// doctrine/dbal isn't installed, so column nullability changes use raw SQL
// instead of Schema::table()->change().
return new class extends Migration
{
    public function up()
    {
        DB::table('products')->whereNull('customer_id')->update(['customer_id' => 3]);
        DB::table('products')->whereNull('season_id')->update(['season_id' => 4]);

        DB::statement('ALTER TABLE products MODIFY style_code VARCHAR(50) NOT NULL');
        DB::statement('ALTER TABLE products MODIFY customer_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE products MODIFY season_id BIGINT UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE products DROP INDEX uniq_products_style_code');
        DB::statement('ALTER TABLE products ADD CONSTRAINT uniq_products_style_customer_season UNIQUE (style_code, product_category_id, customer_id, season_id)');
    }

    public function down()
    {
        DB::statement('ALTER TABLE products DROP INDEX uniq_products_style_customer_season');
        DB::statement('ALTER TABLE products ADD CONSTRAINT uniq_products_style_code UNIQUE (style_code)');

        DB::statement('ALTER TABLE products MODIFY style_code VARCHAR(50) NULL');
        DB::statement('ALTER TABLE products MODIFY customer_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE products MODIFY season_id BIGINT UNSIGNED NULL');
    }
};
