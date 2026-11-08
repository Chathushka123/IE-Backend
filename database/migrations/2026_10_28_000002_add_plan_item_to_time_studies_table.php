<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The planned pair a study was recorded for, when it came from a study plan. Set null (not cascade) if the
// plan item is ever removed: the study is real data and stays.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_studies', function (Blueprint $table) {
            $table->unsignedBigInteger('time_study_plan_item_id')->nullable()->after('template_overridden');
            $table->index('time_study_plan_item_id', 'idx_time_studies_plan_item');
            $table->foreign('time_study_plan_item_id', 'fk_time_studies_plan_item')->references('id')->on('time_study_plan_items')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('time_studies', function (Blueprint $table) {
            $table->dropForeign('fk_time_studies_plan_item');
            $table->dropIndex('idx_time_studies_plan_item');
            $table->dropColumn('time_study_plan_item_id');
        });
    }
};
