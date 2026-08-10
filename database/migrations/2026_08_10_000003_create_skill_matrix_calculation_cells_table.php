<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row per employee x operation cell in a Skill Matrix Insights run.
// Deliberately rich (mean/median/mode/min/max/stddev, not just one lossy
// number) so this table doubles as training data for a future ML model.
return new class extends Migration
{
    public function up()
    {
        Schema::create('skill_matrix_calculation_cells', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('calculation_run_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('operation_id');
            $table->unsignedInteger('study_count')->default(0);
            $table->decimal('mean_efficiency', 6, 2)->nullable();
            $table->decimal('median_efficiency', 6, 2)->nullable();
            // null when mode fell back to mean (no bucket had >1 value)
            $table->decimal('mode_efficiency', 6, 2)->nullable();
            $table->unsignedTinyInteger('mode_bin_size_pct')->nullable();
            $table->boolean('mode_used_fallback_to_mean')->default(false);
            $table->decimal('min_efficiency', 6, 2)->nullable();
            $table->decimal('max_efficiency', 6, 2)->nullable();
            // sample stddev (n-1); null when study_count < 2
            $table->decimal('stddev_efficiency', 8, 4)->nullable();
            $table->enum('calculation_method_used', ['mean', 'median', 'mode']);
            // value actually shown on the grid — deliberate denormalization
            // purely to avoid a CASE on every read; always recomputable from
            // the other columns above.
            $table->decimal('selected_efficiency', 6, 2)->nullable();
            $table->date('first_study_date')->nullable();
            $table->date('last_study_date')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('calculation_run_id', 'fk_smc_cells_run')->references('id')->on('skill_matrix_calculation_runs')->onDelete('cascade');
            $table->foreign('employee_id', 'fk_smc_cells_employee')->references('id')->on('employees');
            $table->foreign('operation_id', 'fk_smc_cells_operation')->references('id')->on('operations');

            $table->unique(['calculation_run_id', 'employee_id', 'operation_id'], 'uq_smc_cells_run_employee_operation');
            $table->index('employee_id', 'idx_smc_cells_employee');
            $table->index('operation_id', 'idx_smc_cells_operation');
        });
    }

    public function down()
    {
        Schema::dropIfExists('skill_matrix_calculation_cells');
    }
};
