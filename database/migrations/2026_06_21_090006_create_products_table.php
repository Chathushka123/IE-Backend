<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('code', 50)->nullable();
            $table->unsignedBigInteger('product_category_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('description', 'uniq_products_description');
            $table->unique('code', 'uniq_products_code');
            $table->foreign('product_category_id', 'fk_products_product_category')->references('id')->on('product_categories');
            $table->foreign('created_by_id', 'fk_products_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_products_updated_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
