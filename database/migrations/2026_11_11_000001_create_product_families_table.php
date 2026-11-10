<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR: product hierarchy is a strict tree — ProductGroup -> ProductFamily -> ProductCategory — rather
// than many-to-many, so a category's group is unambiguous and roll-ups never double-count.
return new class extends Migration
{
    public function up()
    {
        Schema::create('product_families', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50);
            $table->unsignedBigInteger('product_group_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('name', 'uniq_product_families_name');
            $table->unique('code', 'uniq_product_families_code');
            $table->foreign('product_group_id', 'fk_product_families_product_group')->references('id')->on('product_groups');
            $table->foreign('created_by_id', 'fk_product_families_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_product_families_updated_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_families');
    }
};
