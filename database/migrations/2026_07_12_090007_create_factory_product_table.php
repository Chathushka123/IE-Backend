<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('factory_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factory_id');
            $table->unsignedBigInteger('product_id');
            $table->timestamps();

            $table->unique(['factory_id', 'product_id'], 'uniq_factory_product');
            $table->foreign('factory_id', 'fk_factory_product_factory')->references('id')->on('factories')->onDelete('cascade');
            $table->foreign('product_id', 'fk_factory_product_product')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('factory_product');
    }
};
