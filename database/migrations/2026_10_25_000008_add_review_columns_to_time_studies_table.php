<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Who decided on a study that was sent for review, when, and why. NULL on every study that was
// approved automatically (including all pre-template studies). A decision is final: see
// TimeStudyReviewRepository.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_studies', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewed_by_id')->nullable()->after('field_values');
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by_id');
            $table->text('review_note')->nullable()->after('reviewed_at');

            $table->foreign('reviewed_by_id', 'fk_time_studies_reviewed_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('time_studies', function (Blueprint $table) {
            $table->dropForeign('fk_time_studies_reviewed_by');
            $table->dropColumn(['reviewed_by_id', 'reviewed_at', 'review_note']);
        });
    }
};
