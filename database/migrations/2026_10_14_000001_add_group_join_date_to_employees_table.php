<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The date the employee first joined the wider company group, which can predate joining_date
// (the date they joined this factory) for employees transferred in from a sister company.
// Nullable with no backfill — it's unknown for existing employees until someone enters it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('group_join_date')->nullable()->after('confirmation_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('group_join_date');
        });
    }
};
