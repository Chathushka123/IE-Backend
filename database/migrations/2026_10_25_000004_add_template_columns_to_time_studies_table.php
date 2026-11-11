<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Records which template version a study ran under and what the server made of it.
// Defaults (purpose=perf, status=approved, counts_in_matrix=1) deliberately describe the
// pre-template world, so existing rows — and rows inserted directly by seeders and tests —
// keep feeding the skill matrix exactly as before.
// template_version_id stays nullable: NULL means "recorded before templates existed or loaded
// directly"; the application always sets it for new studies.
// smv_source is NULL for rows where it is unknown (all pre-template rows).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_studies', function (Blueprint $table) {
            $table->unsignedBigInteger('template_version_id')->nullable()->after('conducted_by_id');
            $table->boolean('template_overridden')->default(false)->after('template_version_id');
            $table->enum('purpose', ['perf', 'smv'])->default('perf')->after('template_overridden');
            $table->boolean('counts_in_matrix')->default(true)->after('purpose');
            $table->enum('status', ['needs_review', 'approved', 'rejected'])->default('approved')->after('counts_in_matrix');
            $table->unsignedTinyInteger('quality_score')->nullable()->after('status');
            $table->json('quality_breakdown')->nullable()->after('quality_score');
            $table->json('rule_flags')->nullable()->after('quality_breakdown');
            $table->json('field_values')->nullable()->after('rule_flags');
            $table->enum('smv_source', ['operation', 'style_override'])->nullable()->after('smv');

            $table->index('status', 'idx_time_studies_status');
            $table->index(['counts_in_matrix', 'status'], 'idx_time_studies_matrix_status');
            $table->index('template_version_id', 'idx_time_studies_template_version');
            $table->foreign('template_version_id', 'fk_time_studies_template_version')
                ->references('id')->on('time_study_template_versions');
        });
    }

    public function down(): void
    {
        Schema::table('time_studies', function (Blueprint $table) {
            $table->dropForeign('fk_time_studies_template_version');
            $table->dropIndex('idx_time_studies_template_version');
            $table->dropIndex('idx_time_studies_matrix_status');
            $table->dropIndex('idx_time_studies_status');
            $table->dropColumn([
                'template_version_id',
                'template_overridden',
                'purpose',
                'counts_in_matrix',
                'status',
                'quality_score',
                'quality_breakdown',
                'rule_flags',
                'field_values',
                'smv_source',
            ]);
        });
    }
};
