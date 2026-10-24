<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why a lap is excluded (a template rule vs the analyst), and what the analyst decided about a
// lap the outlier rule flagged. is_excluded stays the single flag the calculations read.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_study_laps', function (Blueprint $table) {
            $table->enum('exclusion_source', ['analyst', 'rule'])->nullable()->after('is_excluded');
            $table->boolean('outlier_flagged')->default(false)->after('exclusion_source');
            $table->boolean('outlier_kept')->default(false)->after('outlier_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('time_study_laps', function (Blueprint $table) {
            $table->dropColumn(['exclusion_source', 'outlier_flagged', 'outlier_kept']);
        });
    }
};
