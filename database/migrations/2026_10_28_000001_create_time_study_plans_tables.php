<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A study plan: who will be timed on which operations, under one template version, shared by the analysts
// doing the timing. A plan is a draft while it is being put together, and can only start (active) when it
// satisfies the template's layout. Each item is one operator on one operation; the studies recorded for it
// point back at it (time_studies.time_study_plan_item_id), so progress is counted, never typed in.
// claimed_by_id / claimed_until is a soft lock so two analysts do not time the same pair at once.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_study_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factory_id');
            $table->string('name', 150);
            $table->unsignedBigInteger('template_version_id');
            $table->enum('time_study_type', ['interview_training', 'production_floor'])->nullable();
            $table->unsignedBigInteger('style_id')->nullable();
            $table->unsignedTinyInteger('target_per_pair')->default(1);
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['factory_id', 'status'], 'idx_ts_plans_factory_status');
            $table->index('template_version_id', 'idx_ts_plans_template_version');
            $table->foreign('factory_id', 'fk_ts_plans_factory')->references('id')->on('factories');
            $table->foreign('template_version_id', 'fk_ts_plans_template_version')->references('id')->on('time_study_template_versions');
            $table->foreign('style_id', 'fk_ts_plans_style')->references('id')->on('styles');
            $table->foreign('created_by_id', 'fk_ts_plans_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_ts_plans_updated_by')->references('id')->on('users');
        });

        Schema::create('time_study_plan_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('time_study_plan_id');
            $table->unsignedBigInteger('operation_id');
            $table->unsignedBigInteger('employee_id');
            $table->enum('status', ['pending', 'skipped'])->default('pending');
            $table->string('skip_reason', 255)->nullable();
            $table->unsignedBigInteger('claimed_by_id')->nullable();
            $table->dateTime('claimed_until')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['time_study_plan_id', 'operation_id', 'employee_id'], 'uniq_ts_plan_items_pair');
            $table->index('employee_id', 'idx_ts_plan_items_employee');
            $table->foreign('time_study_plan_id', 'fk_ts_plan_items_plan')->references('id')->on('time_study_plans')->onDelete('cascade');
            $table->foreign('operation_id', 'fk_ts_plan_items_operation')->references('id')->on('operations');
            $table->foreign('employee_id', 'fk_ts_plan_items_employee')->references('id')->on('employees');
            $table->foreign('claimed_by_id', 'fk_ts_plan_items_claimed_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_study_plan_items');
        Schema::dropIfExists('time_study_plans');
    }
};
