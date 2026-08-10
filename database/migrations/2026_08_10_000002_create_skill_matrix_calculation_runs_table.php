<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row = one Skill Matrix Insights "Recalculate & Save" event, scoped to
// the factory-set (FactoryContext::ids()) it was run under. Retention is
// "latest only" per factory-scope — the repository deletes any existing run
// whose factory_ids matches the current scope before inserting a new one,
// so there is no history here by design.
return new class extends Migration
{
    public function up()
    {
        Schema::create('skill_matrix_calculation_runs', function (Blueprint $table) {
            $table->id();
            $table->json('factory_ids')->nullable();
            $table->json('filters');
            $table->unsignedInteger('study_count_total')->default(0);
            $table->unsignedInteger('cell_count')->default(0);
            $table->unsignedBigInteger('calculated_by_id');
            $table->dateTime('calculated_at');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('calculated_by_id', 'fk_smc_runs_calculated_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('skill_matrix_calculation_runs');
    }
};
