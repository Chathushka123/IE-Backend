<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('time_study_downtimes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('time_study_id');
            $table->unsignedInteger('lap_no')->nullable();
            $table->unsignedBigInteger('time_study_downtime_reason_id');
            $table->text('note')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('time_study_id', 'fk_time_study_downtimes_study')->references('id')->on('time_studies')->onDelete('cascade');
            $table->foreign('time_study_downtime_reason_id', 'fk_time_study_downtimes_reason')->references('id')->on('time_study_downtime_reasons');
        });
    }

    public function down()
    {
        Schema::dropIfExists('time_study_downtimes');
    }
};
