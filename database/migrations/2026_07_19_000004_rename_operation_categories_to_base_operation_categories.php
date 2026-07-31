<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: doctrine/dbal is not installed, so Schema::rename()/renameColumn() are unavailable —
// raw SQL is used throughout, mirroring 2026_07_19_000002_rename_skills_to_soft_skills_table
// and 2026_07_19_000003_rename_production_line_and_line_category_tables.
//
// Step 1 of the Operation-family rename (OperationCategory -> BaseOperationCategory).
// This table has no FKs pointing *into* it that need touching here beyond its own
// created_by/updated_by: `operations.operation_category_id` still points at it, and
// InnoDB automatically repoints that constraint at the new table name on RENAME TABLE.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `operation_categories` DROP FOREIGN KEY `fk_operation_categories_created_by`');
        DB::statement('ALTER TABLE `operation_categories` DROP FOREIGN KEY `fk_operation_categories_updated_by`');

        DB::statement('RENAME TABLE `operation_categories` TO `base_operation_categories`');

        DB::statement('ALTER TABLE `base_operation_categories` RENAME INDEX `uniq_operation_categories_name` TO `uniq_base_operation_categories_name`');
        DB::statement('ALTER TABLE `base_operation_categories` RENAME INDEX `uniq_operation_categories_code` TO `uniq_base_operation_categories_code`');
        DB::statement('ALTER TABLE `base_operation_categories` RENAME INDEX `fk_operation_categories_created_by` TO `fk_base_operation_categories_created_by`');
        DB::statement('ALTER TABLE `base_operation_categories` RENAME INDEX `fk_operation_categories_updated_by` TO `fk_base_operation_categories_updated_by`');

        DB::statement('ALTER TABLE `base_operation_categories` ADD CONSTRAINT `fk_base_operation_categories_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `base_operation_categories` ADD CONSTRAINT `fk_base_operation_categories_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `base_operation_categories` DROP FOREIGN KEY `fk_base_operation_categories_created_by`');
        DB::statement('ALTER TABLE `base_operation_categories` DROP FOREIGN KEY `fk_base_operation_categories_updated_by`');

        DB::statement('ALTER TABLE `base_operation_categories` RENAME INDEX `uniq_base_operation_categories_name` TO `uniq_operation_categories_name`');
        DB::statement('ALTER TABLE `base_operation_categories` RENAME INDEX `uniq_base_operation_categories_code` TO `uniq_operation_categories_code`');
        DB::statement('ALTER TABLE `base_operation_categories` RENAME INDEX `fk_base_operation_categories_created_by` TO `fk_operation_categories_created_by`');
        DB::statement('ALTER TABLE `base_operation_categories` RENAME INDEX `fk_base_operation_categories_updated_by` TO `fk_operation_categories_updated_by`');

        DB::statement('RENAME TABLE `base_operation_categories` TO `operation_categories`');

        DB::statement('ALTER TABLE `operation_categories` ADD CONSTRAINT `fk_operation_categories_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `operation_categories` ADD CONSTRAINT `fk_operation_categories_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
    }
};
