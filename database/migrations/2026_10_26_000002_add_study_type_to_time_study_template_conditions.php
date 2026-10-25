<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: raw ALTER because doctrine/dbal is not installed. A template can now be scoped to a kind of
// time study (interview & training, production floor); ref_id holds the App\Support\TimeStudy\StudyTypes id.
return new class extends Migration
{
    private const WITH = "'operation','base_operation','base_operation_category','machine_type','machine_category','product_category','product_group','study_type'";
    private const WITHOUT = "'operation','base_operation','base_operation_category','machine_type','machine_category','product_category','product_group'";

    public function up(): void
    {
        DB::statement('ALTER TABLE `time_study_template_conditions` MODIFY `field` ENUM(' . self::WITH . ') NOT NULL');
    }

    public function down(): void
    {
        DB::table('time_study_template_conditions')->where('field', 'study_type')->delete();
        DB::statement('ALTER TABLE `time_study_template_conditions` MODIFY `field` ENUM(' . self::WITHOUT . ') NOT NULL');
    }
};
