<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('line_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_line_id');
            $table->unsignedBigInteger('product_id');
            // Nullable at the DB level only so resequence() can null-out before reassigning
            // without tripping the unique constraint; the app validator still requires it.
            $table->unsignedInteger('sequence_no')->nullable();
            $table->unsignedInteger('planned_quantity');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'on_hold', 'cancelled'])->default('planned');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['production_line_id', 'sequence_no'], 'uniq_line_plans_line_sequence');
            $table->index('product_id', 'idx_line_plans_product');
            $table->index('status', 'idx_line_plans_status');

            $table->foreign('production_line_id', 'fk_line_plans_production_line')->references('id')->on('production_lines');
            $table->foreign('product_id', 'fk_line_plans_product')->references('id')->on('products');
            $table->foreign('created_by_id', 'fk_line_plans_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_line_plans_updated_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('line_plans');
    }
};
