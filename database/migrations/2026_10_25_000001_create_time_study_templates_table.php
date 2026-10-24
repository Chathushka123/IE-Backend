<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A template is the stable identity of a time-study protocol; what a study must satisfy lives
// in its immutable versions (next migration). Templates are shared across factories, so there
// is deliberately no factory_id. `name` is unique across archived templates too: an archived
// name stays reserved so old studies never point at an ambiguous label.
// `system_key` marks templates the application itself relies on (e.g. 'legacy').
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_study_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('system_key', 50)->nullable();
            $table->enum('purpose', ['perf', 'smv'])->default('perf');
            $table->boolean('manual_only')->default(false);
            $table->dateTime('archived_at')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('name', 'uniq_time_study_templates_name');
            $table->unique('system_key', 'uniq_time_study_templates_system_key');
            $table->foreign('created_by_id', 'fk_time_study_templates_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_time_study_templates_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_study_templates');
    }
};
