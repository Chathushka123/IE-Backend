<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: doctrine/dbal is not installed, so Schema::rename()/renameColumn() are unavailable —
// raw SQL is used throughout, mirroring 2026_07_19_000000_rename_description_to_name_in_ie_module_tables.
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Renames `skills` -> `soft_skills`. This table has no incoming FKs that need
     * touching here: `operation_grading_skill.skill_id` still points at it, and
     * InnoDB automatically repoints that constraint at the new table name on
     * RENAME TABLE — that pivot table is out of scope for this pass.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `skills` DROP FOREIGN KEY `fk_skills_created_by`');
        DB::statement('ALTER TABLE `skills` DROP FOREIGN KEY `fk_skills_updated_by`');

        DB::statement('RENAME TABLE `skills` TO `soft_skills`');

        DB::statement('ALTER TABLE `soft_skills` RENAME INDEX `uniq_skills_name` TO `uniq_soft_skills_name`');
        DB::statement('ALTER TABLE `soft_skills` RENAME INDEX `uniq_skills_code` TO `uniq_soft_skills_code`');
        DB::statement('ALTER TABLE `soft_skills` RENAME INDEX `fk_skills_created_by` TO `fk_soft_skills_created_by`');
        DB::statement('ALTER TABLE `soft_skills` RENAME INDEX `fk_skills_updated_by` TO `fk_soft_skills_updated_by`');

        DB::statement('ALTER TABLE `soft_skills` ADD CONSTRAINT `fk_soft_skills_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `soft_skills` ADD CONSTRAINT `fk_soft_skills_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `soft_skills` DROP FOREIGN KEY `fk_soft_skills_created_by`');
        DB::statement('ALTER TABLE `soft_skills` DROP FOREIGN KEY `fk_soft_skills_updated_by`');

        DB::statement('ALTER TABLE `soft_skills` RENAME INDEX `uniq_soft_skills_name` TO `uniq_skills_name`');
        DB::statement('ALTER TABLE `soft_skills` RENAME INDEX `uniq_soft_skills_code` TO `uniq_skills_code`');
        DB::statement('ALTER TABLE `soft_skills` RENAME INDEX `fk_soft_skills_created_by` TO `fk_skills_created_by`');
        DB::statement('ALTER TABLE `soft_skills` RENAME INDEX `fk_soft_skills_updated_by` TO `fk_skills_updated_by`');

        DB::statement('RENAME TABLE `soft_skills` TO `skills`');

        DB::statement('ALTER TABLE `skills` ADD CONSTRAINT `fk_skills_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `skills` ADD CONSTRAINT `fk_skills_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
    }
};
