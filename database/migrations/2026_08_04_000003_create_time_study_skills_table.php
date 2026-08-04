<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Snapshot of the soft skills mapped to the selected operation at study time
        // (Operation::softSkills() is many-to-many — 0, 1, or several per operation).
        Schema::create('time_study_skills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('time_study_id');
            $table->unsignedBigInteger('soft_skill_id');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['time_study_id', 'soft_skill_id'], 'uniq_time_study_skills_study_skill');
            $table->foreign('time_study_id', 'fk_time_study_skills_study')->references('id')->on('time_studies')->onDelete('cascade');
            $table->foreign('soft_skill_id', 'fk_time_study_skills_skill')->references('id')->on('soft_skills');
        });
    }

    public function down()
    {
        Schema::dropIfExists('time_study_skills');
    }
};
