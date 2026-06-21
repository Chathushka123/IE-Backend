<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('operation_grading_skill', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_grading_id');
            $table->unsignedBigInteger('skill_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['operation_grading_id', 'skill_id'], 'uniq_operation_grading_skill_grading_skill');
            $table->foreign('operation_grading_id', 'fk_operation_grading_skill_grading')->references('id')->on('operation_gradings');
            $table->foreign('skill_id', 'fk_operation_grading_skill_skill')->references('id')->on('skills');
            $table->foreign('created_by_id', 'fk_operation_grading_skill_created_by')->references('id')->on('users');
            $table->foreign('updated_by_id', 'fk_operation_grading_skill_updated_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('operation_grading_skill');
    }
};
