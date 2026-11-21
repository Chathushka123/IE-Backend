<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR: the standard T&A (time & action) list is reference data owned by planners, kept in its own
// table instead of being hard-coded, so the ~70 standard NPI actions can be re-ordered, re-assigned
// and extended without a release. Each style gets its own copy (style_ta_actions), so editing the
// master never rewrites a running style's plan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ta_template_actions', function (Blueprint $table) {
            $table->id();
            // Free text on purpose (e.g. "Development", "Plant QCO") — can become master data later.
            $table->string('phase', 50);
            // 1..n within a phase.
            $table->unsignedInteger('sequence')->default(1);
            $table->string('name', 255);
            // Nullable: planners map each action to a designation after the initial load.
            $table->unsignedBigInteger('designation_id')->nullable();
            // The responsibility exactly as written in the source sheet, shown as a hint until mapped.
            $table->string('responsibility_note', 150)->nullable();
            // Free text on purpose (-1, 6, S-14, "Presign off +2day", "Weekly", …).
            $table->string('process_time', 100)->nullable();
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['phase', 'name'], 'uniq_ta_template_actions_phase_name');
            $table->index(['phase', 'sequence'], 'idx_ta_template_actions_phase_sequence');

            $table->foreign('designation_id', 'fk_ta_template_actions_designation')->references('id')->on('designations');
            $table->foreign('created_by_id', 'fk_ta_template_actions_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_ta_template_actions_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ta_template_actions');
    }
};
