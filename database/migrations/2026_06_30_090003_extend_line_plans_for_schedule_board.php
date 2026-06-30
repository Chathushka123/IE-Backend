<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // doctrine/dbal isn't installed, so column-type changes use raw SQL
    // instead of Schema::table()->change().
    public function up()
    {
        DB::statement('ALTER TABLE line_plans MODIFY product_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE line_plans MODIFY planned_quantity INT UNSIGNED NULL');
        DB::statement('ALTER TABLE line_plans MODIFY planned_start_date DATETIME NULL');
        DB::statement('ALTER TABLE line_plans MODIFY planned_end_date DATETIME NULL');
        DB::statement('ALTER TABLE line_plans MODIFY actual_start_date DATETIME NULL');
        DB::statement('ALTER TABLE line_plans MODIFY actual_end_date DATETIME NULL');

        Schema::table('line_plans', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('status');
            $table->boolean('is_changeover')->default(false)->after('notes');
        });
    }

    public function down()
    {
        Schema::table('line_plans', function (Blueprint $table) {
            $table->dropColumn(['notes', 'is_changeover']);
        });

        DB::statement('ALTER TABLE line_plans MODIFY planned_start_date DATE NULL');
        DB::statement('ALTER TABLE line_plans MODIFY planned_end_date DATE NULL');
        DB::statement('ALTER TABLE line_plans MODIFY actual_start_date DATE NULL');
        DB::statement('ALTER TABLE line_plans MODIFY actual_end_date DATE NULL');
        // NOT NULL revert only safe if no changeover rows (null product/quantity) exist.
        DB::statement('ALTER TABLE line_plans MODIFY planned_quantity INT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE line_plans MODIFY product_id BIGINT UNSIGNED NOT NULL');
    }
};
