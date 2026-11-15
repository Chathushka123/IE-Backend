<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR: a style's T&A actions are a per-style copy of the standard list (not a live reference), so a
// style can add, drop or re-date actions without touching the master or other styles. The link back
// to the template row is kept (nullable) only to stop "apply standard T&A" re-adding the same action.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('style_ta_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('style_id');
            $table->unsignedBigInteger('ta_template_action_id')->nullable();
            $table->string('phase', 50);
            $table->unsignedInteger('sequence')->default(1);
            $table->string('name', 255);
            $table->unsignedBigInteger('designation_id')->nullable();
            // Independent of designation: anyone can be assigned, not only holders of the designation.
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('process_time', 100)->nullable();
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->string('remarks', 500)->nullable();

            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['style_id', 'phase', 'name'], 'uniq_style_ta_actions_style_phase_name');
            $table->index(['style_id', 'phase', 'sequence'], 'idx_style_ta_actions_style_phase_sequence');
            $table->index('planned_date', 'idx_style_ta_actions_planned_date');
            $table->index('ta_template_action_id', 'idx_style_ta_actions_template');
            $table->index('designation_id', 'idx_style_ta_actions_designation');
            $table->index('employee_id', 'idx_style_ta_actions_employee');

            $table->foreign('style_id', 'fk_style_ta_actions_style')->references('id')->on('styles')->cascadeOnDelete();
            $table->foreign('ta_template_action_id', 'fk_style_ta_actions_template')->references('id')->on('ta_template_actions')->nullOnDelete();
            $table->foreign('designation_id', 'fk_style_ta_actions_designation')->references('id')->on('designations');
            $table->foreign('employee_id', 'fk_style_ta_actions_employee')->references('id')->on('employees');
            $table->foreign('created_by_id', 'fk_style_ta_actions_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_style_ta_actions_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('style_ta_actions');
    }
};
