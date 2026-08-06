<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('time_study_laps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('time_study_id');
            $table->unsignedInteger('lap_no');
            // Gross wall-clock duration of the lap (Hold time is paused out of this already).
            $table->unsignedInteger('duration_ms');
            // Sum of any reason-tagged down time that fell inside this lap.
            $table->unsignedInteger('down_time_ms')->default(0);
            // duration_ms - down_time_ms — this is what feeds avg/fastest/slowest cycle stats.
            $table->unsignedInteger('net_ms');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['time_study_id', 'lap_no'], 'uniq_time_study_laps_study_lap');
            $table->foreign('time_study_id', 'fk_time_study_laps_study')->references('id')->on('time_studies')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('time_study_laps');
    }
};
