<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The user who actually conducted the study, which can differ from the logged-in user who
// saved it (e.g. an IE entering a study on behalf of a colleague). Defaults to the saving
// user, so existing rows are backfilled from created_by_id. Nullable because some legacy
// rows have no created_by_id either.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_studies', function (Blueprint $table) {
            $table->unsignedBigInteger('conducted_by_id')->nullable()->after('employee_id');
            $table->index('conducted_by_id', 'idx_time_studies_conducted_by');
            $table->foreign('conducted_by_id', 'fk_time_studies_conducted_by')->references('id')->on('users');
        });

        DB::table('time_studies')->whereNull('conducted_by_id')->update(['conducted_by_id' => DB::raw('created_by_id')]);
    }

    public function down(): void
    {
        Schema::table('time_studies', function (Blueprint $table) {
            $table->dropForeign('fk_time_studies_conducted_by');
            $table->dropIndex('idx_time_studies_conducted_by');
            $table->dropColumn('conducted_by_id');
        });
    }
};
