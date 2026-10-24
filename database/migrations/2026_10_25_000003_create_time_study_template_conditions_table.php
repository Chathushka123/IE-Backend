<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Scope of a template version. Two conditions on the same field mean "any of" (OR);
// conditions on different fields are ANDed. ref_id points at the master row named by `field`
// (operations, base_operations, base_operation_categories, machine_types, product_categories,
// product_groups) — by id, so renames never break a template.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_study_template_conditions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('time_study_template_version_id');
            $table->enum('field', [
                'operation',
                'base_operation',
                'base_operation_category',
                'machine_type',
                'product_category',
                'product_group',
            ]);
            $table->unsignedBigInteger('ref_id');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('time_study_template_version_id', 'idx_ts_template_conditions_version');
            $table->index(['field', 'ref_id'], 'idx_ts_template_conditions_field_ref');
            $table->foreign('time_study_template_version_id', 'fk_ts_template_conditions_version')
                ->references('id')->on('time_study_template_versions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_study_template_conditions');
    }
};
