<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The "responsibility as written in the source sheet" hint was only ever a mapping aid and is no
// longer shown or exported. It is dropped in a new migration (not by editing
// 2026_11_15_000001/000003) because those have already been run.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ta_template_actions', 'responsibility_note')) {
            Schema::table('ta_template_actions', function (Blueprint $table) {
                $table->dropColumn('responsibility_note');
            });
        }
    }

    // Restores the (empty) column only; the original sheet text is not recoverable from here.
    public function down(): void
    {
        if (!Schema::hasColumn('ta_template_actions', 'responsibility_note')) {
            Schema::table('ta_template_actions', function (Blueprint $table) {
                $table->string('responsibility_note', 150)->nullable()->after('designation_id');
            });
        }
    }
};
