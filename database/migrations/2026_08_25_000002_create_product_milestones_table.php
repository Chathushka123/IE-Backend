<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR: apparel-industry order milestones (cutting -> sewing -> finishing ->
// inspection -> ship-out) describe the lifecycle of a style/order as a whole,
// not a specific team's time slot, so they live 1:1 against products rather
// than on team_plans.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');

            // PCD/PCS - Planned Cutting Date/Start, ACD - Actual Cutting Date
            $table->date('planned_cut_date')->nullable();
            $table->date('actual_cut_date')->nullable();

            // PSD - Production Start Date
            $table->date('planned_production_start_date')->nullable();
            $table->date('actual_production_start_date')->nullable();

            // PED - Production End Date
            $table->date('planned_production_end_date')->nullable();
            $table->date('actual_production_end_date')->nullable();

            // FND - Finishing Date
            $table->date('planned_finishing_date')->nullable();
            $table->date('actual_finishing_date')->nullable();

            // FRI - Final Random Inspection Date
            $table->date('planned_final_inspection_date')->nullable();
            $table->date('actual_final_inspection_date')->nullable();

            // EXF/EXW - Ex-Factory Date
            $table->date('planned_ex_factory_date')->nullable();
            $table->date('actual_ex_factory_date')->nullable();

            // CRD - Cargo Received Date
            $table->date('planned_cargo_received_date')->nullable();
            $table->date('actual_cargo_received_date')->nullable();

            // ETD - Estimated Time of Departure
            $table->date('planned_etd')->nullable();
            $table->date('actual_etd')->nullable();

            // ETA - Estimated Time of Arrival
            $table->date('planned_eta')->nullable();
            $table->date('actual_eta')->nullable();

            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('product_id', 'uniq_product_milestones_product');

            $table->foreign('product_id', 'fk_product_milestones_product')->references('id')->on('products');
            $table->foreign('created_by_id', 'fk_product_milestones_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_product_milestones_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_milestones');
    }
};
