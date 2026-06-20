<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOperationGradingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('operation_gradings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_id');
            $table->unsignedBigInteger('product_category_id');
            $table->unsignedBigInteger('grade_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['operation_id', 'product_category_id'], 'uniq_operation_gradings_operation_product_category');
            $table->foreign('operation_id', 'fk_operation_gradings_operation')->references('id')->on('operations');
            $table->foreign('product_category_id', 'fk_operation_gradings_product_category')->references('id')->on('product_categories');
            $table->foreign('grade_id', 'fk_operation_gradings_grade')->references('id')->on('operation_grades');
            $table->foreign('created_by_id', 'fk_operation_gradings_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_operation_gradings_updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('operation_gradings');
    }
}
