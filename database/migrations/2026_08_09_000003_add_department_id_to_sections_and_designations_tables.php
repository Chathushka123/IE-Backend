<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// department_id is nullable at the DB/validation layer on purpose — a section/designation
// belongs to at most one department, but the frontend is the one enforcing "must pick a
// department" on create/update, not the backend. This lets existing rows (created before
// this column existed) stay valid without a backfill.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('code');
            $table->foreign('department_id', 'fk_sections_department')->references('id')->on('departments');
            $table->index('department_id', 'idx_sections_department');
        });

        Schema::table('designations', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('code');
            $table->foreign('department_id', 'fk_designations_department')->references('id')->on('departments');
            $table->index('department_id', 'idx_designations_department');
        });
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->dropForeign('fk_designations_department');
            $table->dropColumn('department_id');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropForeign('fk_sections_department');
            $table->dropColumn('department_id');
        });
    }
};
