<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets each operation choose how its Skill Matrix Insights cell value is
// summarized (mean/median/mode) instead of always averaging, since some
// operations' time-study data is skewed/outlier-prone. mode_bin_size_pct
// controls the bucket width used by the mode calculation (range 1-10
// enforced at the validator layer, matching how this table's other
// decimals aren't DB-constrained either).
return new class extends Migration
{
    public function up()
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->enum('calculation_method', ['mean', 'median', 'mode'])
                ->default('mean')
                ->after('expected_lower_mid_level_efficiency');
            $table->unsignedTinyInteger('mode_bin_size_pct')
                ->default(1)
                ->after('calculation_method');
        });
    }

    public function down()
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn(['calculation_method', 'mode_bin_size_pct']);
        });
    }
};
