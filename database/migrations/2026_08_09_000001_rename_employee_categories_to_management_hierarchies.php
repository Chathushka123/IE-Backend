<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: doctrine/dbal is not installed, so Schema::rename()/renameColumn() are unavailable —
// raw SQL is used throughout, mirroring 2026_07_19_000003_rename_production_line_and_line_category_tables.
//
// Renames:
//   employee_categories -> management_hierarchies
//   employees.category_id -> employees.management_hierarchy_id (now pointing at management_hierarchies)
return new class extends Migration
{
    public function up(): void
    {
        // ---- management_hierarchies (from employee_categories) ------------------------
        DB::statement('ALTER TABLE `employee_categories` DROP FOREIGN KEY `fk_employee_categories_created_by`');
        DB::statement('ALTER TABLE `employee_categories` DROP FOREIGN KEY `fk_employee_categories_updated_by`');

        DB::statement('RENAME TABLE `employee_categories` TO `management_hierarchies`');

        DB::statement('ALTER TABLE `management_hierarchies` RENAME INDEX `uniq_employee_categories_name` TO `uniq_management_hierarchies_name`');
        DB::statement('ALTER TABLE `management_hierarchies` RENAME INDEX `uniq_employee_categories_code` TO `uniq_management_hierarchies_code`');
        DB::statement('ALTER TABLE `management_hierarchies` RENAME INDEX `fk_employee_categories_created_by` TO `fk_management_hierarchies_created_by`');
        DB::statement('ALTER TABLE `management_hierarchies` RENAME INDEX `fk_employee_categories_updated_by` TO `fk_management_hierarchies_updated_by`');

        DB::statement('ALTER TABLE `management_hierarchies` ADD CONSTRAINT `fk_management_hierarchies_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `management_hierarchies` ADD CONSTRAINT `fk_management_hierarchies_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');

        // ---- employees.category_id -> employees.management_hierarchy_id ----------------
        DB::statement('ALTER TABLE `employees` DROP FOREIGN KEY `fk_employees_category`');

        DB::statement('ALTER TABLE `employees` CHANGE `category_id` `management_hierarchy_id` BIGINT UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE `employees` RENAME INDEX `fk_employees_category` TO `fk_employees_management_hierarchy`');

        DB::statement('ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_management_hierarchy` FOREIGN KEY (`management_hierarchy_id`) REFERENCES `management_hierarchies` (`id`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ---- employees (undo) ------------------------------------------------------------
        DB::statement('ALTER TABLE `employees` DROP FOREIGN KEY `fk_employees_management_hierarchy`');
        DB::statement('ALTER TABLE `employees` CHANGE `management_hierarchy_id` `category_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `employees` RENAME INDEX `fk_employees_management_hierarchy` TO `fk_employees_category`');
        // fk_employees_category re-added below, once `employee_categories` exists again.

        // ---- management_hierarchies -> employee_categories (undo) ------------------------
        DB::statement('ALTER TABLE `management_hierarchies` DROP FOREIGN KEY `fk_management_hierarchies_created_by`');
        DB::statement('ALTER TABLE `management_hierarchies` DROP FOREIGN KEY `fk_management_hierarchies_updated_by`');

        DB::statement('RENAME TABLE `management_hierarchies` TO `employee_categories`');

        DB::statement('ALTER TABLE `employee_categories` RENAME INDEX `uniq_management_hierarchies_name` TO `uniq_employee_categories_name`');
        DB::statement('ALTER TABLE `employee_categories` RENAME INDEX `uniq_management_hierarchies_code` TO `uniq_employee_categories_code`');
        DB::statement('ALTER TABLE `employee_categories` RENAME INDEX `fk_management_hierarchies_created_by` TO `fk_employee_categories_created_by`');
        DB::statement('ALTER TABLE `employee_categories` RENAME INDEX `fk_management_hierarchies_updated_by` TO `fk_employee_categories_updated_by`');

        DB::statement('ALTER TABLE `employee_categories` ADD CONSTRAINT `fk_employee_categories_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `employee_categories` ADD CONSTRAINT `fk_employee_categories_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');

        // ---- deferred FK: now that employee_categories exists again -----------------------
        DB::statement('ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_category` FOREIGN KEY (`category_id`) REFERENCES `employee_categories` (`id`)');
    }
};
