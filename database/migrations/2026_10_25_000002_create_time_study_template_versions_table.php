<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR: rules, custom fields, approval mode etc. live in one JSON `definition` per version —
// they are only ever read whole, together with the version. The scope conditions that
// matching queries filter and join on get their own indexed table instead.
// Published versions are immutable (enforced in the model); a template has at most one draft
// version (enforced in the application), whose version_no stays NULL until it is published.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_study_template_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('time_study_template_id');
            $table->unsignedInteger('version_no')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->json('definition');
            $table->dateTime('published_at')->nullable();
            $table->unsignedBigInteger('published_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['time_study_template_id', 'version_no'], 'uniq_ts_template_versions_template_version_no');
            $table->foreign('time_study_template_id', 'fk_ts_template_versions_template')->references('id')->on('time_study_templates');
            $table->foreign('published_by_id', 'fk_ts_template_versions_published_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_study_template_versions');
    }
};
