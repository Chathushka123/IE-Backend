<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('time_studies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factory_id')->nullable();
            $table->date('study_date');
            $table->enum('time_study_type', ['interview_training', 'production_floor']);
            $table->unsignedBigInteger('operation_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('employee_id');

            // Snapshotted from the selected operation/product at save time — kept
            // denormalized so analytics can group by category/machine/SMV without
            // joining through operations that may later be re-mapped or resequenced.
            $table->unsignedBigInteger('product_category_id')->nullable();
            $table->unsignedBigInteger('machine_type_id')->nullable();
            $table->decimal('smv', 8, 4)->nullable();

            $table->unsignedInteger('total_productive_ms')->default(0);
            $table->unsignedInteger('total_down_time_ms')->default(0);
            $table->unsignedInteger('total_hold_ms')->default(0);
            $table->unsignedInteger('total_cycle_ms')->default(0);
            $table->unsignedInteger('avg_cycle_ms')->nullable();
            $table->unsignedInteger('fastest_cycle_ms')->nullable();
            $table->unsignedInteger('slowest_cycle_ms')->nullable();
            $table->decimal('efficiency_pct', 6, 2)->nullable();

            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('factory_id', 'fk_time_studies_factory')->references('id')->on('factories');
            $table->foreign('operation_id', 'fk_time_studies_operation')->references('id')->on('operations');
            $table->foreign('product_id', 'fk_time_studies_product')->references('id')->on('products');
            $table->foreign('employee_id', 'fk_time_studies_employee')->references('id')->on('employees');
            $table->foreign('product_category_id', 'fk_time_studies_product_category')->references('id')->on('product_categories');
            $table->foreign('machine_type_id', 'fk_time_studies_machine_type')->references('id')->on('machine_types');
            $table->foreign('created_by_id', 'fk_time_studies_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_time_studies_updated_by')->references('id')->on('users');

            $table->index(['employee_id', 'operation_id'], 'idx_time_studies_employee_operation');
        });
    }

    public function down()
    {
        Schema::dropIfExists('time_studies');
    }
};
