<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('code', 50)->nullable();
            $table->unsignedBigInteger('product_group_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('description', 'uniq_product_categories_description');
            $table->foreign('product_group_id', 'fk_product_categories_product_group')->references('id')->on('product_groups');
            $table->foreign('created_by_id', 'fk_product_categories_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_product_categories_updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_categories');
    }
}
