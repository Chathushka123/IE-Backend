<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: raw ALTER because doctrine/dbal is not installed, so Blueprint::change() on an enum is
// unavailable. A template can now be scoped to a machine category (machine types belong to one).
return new class extends Migration
{
    private const WITH = "'operation','base_operation','base_operation_category','machine_type','machine_category','product_category','product_group'";
    private const WITHOUT = "'operation','base_operation','base_operation_category','machine_type','product_category','product_group'";

    public function up(): void
    {
        DB::statement('ALTER TABLE `time_study_template_conditions` MODIFY `field` ENUM(' . self::WITH . ') NOT NULL');
    }

    public function down(): void
    {
        DB::table('time_study_template_conditions')->where('field', 'machine_category')->delete();
        DB::statement('ALTER TABLE `time_study_template_conditions` MODIFY `field` ENUM(' . self::WITHOUT . ') NOT NULL');
    }
};
