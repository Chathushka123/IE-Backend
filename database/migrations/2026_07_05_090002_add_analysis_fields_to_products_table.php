<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // doctrine/dbal isn't installed, so the code -> style_code rename uses raw SQL
    // instead of Schema::table()->renameColumn().
    public function up()
    {
        DB::statement('ALTER TABLE products CHANGE code style_code VARCHAR(50) NULL');
        DB::statement('ALTER TABLE products RENAME INDEX uniq_products_code TO uniq_products_style_code');

        Schema::table('products', function (Blueprint $table) {
            $table->string('style_description')->nullable()->after('description');
            $table->unsignedBigInteger('customer_id')->nullable()->after('product_category_id');
            $table->string('season', 50)->nullable()->after('customer_id');
            $table->json('colors')->nullable()->after('season');
            $table->json('sizes')->nullable()->after('colors');
            $table->date('customer_requested_delivery_date')->nullable()->after('sizes');
            $table->decimal('planned_efficiency_pct', 5, 2)->nullable()->after('customer_requested_delivery_date');

            $table->index('customer_id', 'idx_products_customer');
            $table->foreign('customer_id', 'fk_products_customer')->references('id')->on('customers');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign('fk_products_customer');
            $table->dropIndex('idx_products_customer');
            $table->dropColumn([
                'style_description',
                'customer_id',
                'season',
                'colors',
                'sizes',
                'customer_requested_delivery_date',
                'planned_efficiency_pct',
            ]);
        });

        DB::statement('ALTER TABLE products RENAME INDEX uniq_products_style_code TO uniq_products_code');
        DB::statement('ALTER TABLE products CHANGE style_code code VARCHAR(50) NULL');
    }
};
