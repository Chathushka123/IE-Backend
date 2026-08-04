<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('time_study_downtime_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('name', 'uniq_time_study_downtime_reasons_name');
            $table->unique('code', 'uniq_time_study_downtime_reasons_code');
            $table->foreign('created_by_id', 'fk_ts_downtime_reasons_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_ts_downtime_reasons_updated_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('time_study_downtime_reasons');
    }
};
