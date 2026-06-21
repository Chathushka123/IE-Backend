<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_operation_gradings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('operation_grading_id');
            // Nullable at the DB level only so resequence() can null-out before reassigning
            // without tripping the unique constraint; the app validator still requires it.
            $table->unsignedInteger('sequence_no')->nullable();
            $table->decimal('smv', 8, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['product_id', 'operation_grading_id'], 'uniq_product_op_gradings_product_grading');
            $table->unique(['product_id', 'sequence_no'], 'uniq_product_op_gradings_product_sequence');
            $table->foreign('product_id', 'fk_product_op_gradings_product')->references('id')->on('products');
            $table->foreign('operation_grading_id', 'fk_product_op_gradings_grading')->references('id')->on('operation_gradings');
            $table->foreign('created_by_id', 'fk_product_op_gradings_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_product_op_gradings_updated_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_operation_gradings');
    }
};
