<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueCodeToSkillsAndOperationsTables extends Migration
{
    public function up()
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->unique('code', 'uniq_skills_code');
        });

        Schema::table('operation_categories', function (Blueprint $table) {
            $table->unique('code', 'uniq_operation_categories_code');
        });

        Schema::table('operations', function (Blueprint $table) {
            $table->unique('code', 'uniq_operations_code');
        });

        Schema::table('operation_grades', function (Blueprint $table) {
            $table->unique('code', 'uniq_operation_grades_code');
        });
    }

    public function down()
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropUnique('uniq_skills_code');
        });

        Schema::table('operation_categories', function (Blueprint $table) {
            $table->dropUnique('uniq_operation_categories_code');
        });

        Schema::table('operations', function (Blueprint $table) {
            $table->dropUnique('uniq_operations_code');
        });

        Schema::table('operation_grades', function (Blueprint $table) {
            $table->dropUnique('uniq_operation_grades_code');
        });
    }
}
