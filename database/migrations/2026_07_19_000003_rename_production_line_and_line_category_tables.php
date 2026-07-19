<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: doctrine/dbal is not installed, so Schema::rename()/renameColumn() are unavailable —
// raw SQL is used throughout, mirroring 2026_07_19_000000_rename_description_to_name_in_ie_module_tables.
//
// Renames, in dependency order:
//   line_categories -> sections
//   production_lines -> teams (category_id -> section_id, now pointing at sections)
//   employees: production_line_id -> team_id, base_line_id -> base_team_id
//   line_plans: production_line_id -> team_id
//
// Each block drops the FKs owned by that table, renames the column(s)/table, renames the
// indexes to match the new `{table}` naming, then re-adds the FKs against the (possibly
// renamed) target table. down() mirrors this in reverse, but must defer re-adding the FKs
// that target `line_categories`/`production_lines` until those tables actually exist again
// (i.e. until the sections/teams blocks below have been unwound) — see the "deferred" block
// at the end of down().
return new class extends Migration
{
    public function up(): void
    {
        // ---- sections (from line_categories) --------------------------------------
        DB::statement('ALTER TABLE `line_categories` DROP FOREIGN KEY `fk_line_categories_created_by`');
        DB::statement('ALTER TABLE `line_categories` DROP FOREIGN KEY `fk_line_categories_updated_by`');

        DB::statement('RENAME TABLE `line_categories` TO `sections`');

        DB::statement('ALTER TABLE `sections` RENAME INDEX `uniq_line_categories_name` TO `uniq_sections_name`');
        DB::statement('ALTER TABLE `sections` RENAME INDEX `fk_line_categories_created_by` TO `fk_sections_created_by`');
        DB::statement('ALTER TABLE `sections` RENAME INDEX `fk_line_categories_updated_by` TO `fk_sections_updated_by`');

        DB::statement('ALTER TABLE `sections` ADD CONSTRAINT `fk_sections_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `sections` ADD CONSTRAINT `fk_sections_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');

        // ---- teams (from production_lines) -----------------------------------------
        DB::statement('ALTER TABLE `production_lines` DROP FOREIGN KEY `fk_production_lines_category`');
        DB::statement('ALTER TABLE `production_lines` DROP FOREIGN KEY `fk_production_lines_department`');
        DB::statement('ALTER TABLE `production_lines` DROP FOREIGN KEY `fk_production_lines_created_by`');
        DB::statement('ALTER TABLE `production_lines` DROP FOREIGN KEY `fk_production_lines_updated_by`');
        DB::statement('ALTER TABLE `production_lines` DROP FOREIGN KEY `fk_production_lines_factory`');

        DB::statement('ALTER TABLE `production_lines` CHANGE `category_id` `section_id` BIGINT UNSIGNED NOT NULL');

        DB::statement('RENAME TABLE `production_lines` TO `teams`');

        DB::statement('ALTER TABLE `teams` RENAME INDEX `uniq_production_lines_name` TO `uniq_teams_name`');
        DB::statement('ALTER TABLE `teams` RENAME INDEX `fk_production_lines_category` TO `fk_teams_section`');
        DB::statement('ALTER TABLE `teams` RENAME INDEX `fk_production_lines_department` TO `fk_teams_department`');
        DB::statement('ALTER TABLE `teams` RENAME INDEX `fk_production_lines_created_by` TO `fk_teams_created_by`');
        DB::statement('ALTER TABLE `teams` RENAME INDEX `fk_production_lines_updated_by` TO `fk_teams_updated_by`');
        DB::statement('ALTER TABLE `teams` RENAME INDEX `idx_production_lines_factory` TO `idx_teams_factory`');

        DB::statement('ALTER TABLE `teams` ADD CONSTRAINT `fk_teams_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)');
        DB::statement('ALTER TABLE `teams` ADD CONSTRAINT `fk_teams_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)');
        DB::statement('ALTER TABLE `teams` ADD CONSTRAINT `fk_teams_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `teams` ADD CONSTRAINT `fk_teams_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `teams` ADD CONSTRAINT `fk_teams_factory` FOREIGN KEY (`factory_id`) REFERENCES `factories` (`id`)');

        // ---- employees ----------------------------------------------------------------
        DB::statement('ALTER TABLE `employees` DROP FOREIGN KEY `fk_employees_production_line`');
        DB::statement('ALTER TABLE `employees` DROP FOREIGN KEY `fk_employees_base_line`');

        DB::statement('ALTER TABLE `employees` CHANGE `production_line_id` `team_id` BIGINT UNSIGNED DEFAULT NULL');
        DB::statement('ALTER TABLE `employees` CHANGE `base_line_id` `base_team_id` BIGINT UNSIGNED DEFAULT NULL');

        DB::statement('ALTER TABLE `employees` RENAME INDEX `idx_employees_line` TO `idx_employees_team`');
        DB::statement('ALTER TABLE `employees` RENAME INDEX `fk_employees_base_line` TO `fk_employees_base_team`');

        DB::statement('ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)');
        DB::statement('ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_base_team` FOREIGN KEY (`base_team_id`) REFERENCES `teams` (`id`)');

        // ---- line_plans -----------------------------------------------------------------
        DB::statement('ALTER TABLE `line_plans` DROP FOREIGN KEY `fk_line_plans_production_line`');

        DB::statement('ALTER TABLE `line_plans` CHANGE `production_line_id` `team_id` BIGINT UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE `line_plans` RENAME INDEX `uniq_line_plans_line_sequence` TO `uniq_line_plans_team_sequence`');

        DB::statement('ALTER TABLE `line_plans` ADD CONSTRAINT `fk_line_plans_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ---- line_plans (undo) -----------------------------------------------------------
        DB::statement('ALTER TABLE `line_plans` DROP FOREIGN KEY `fk_line_plans_team`');
        DB::statement('ALTER TABLE `line_plans` CHANGE `team_id` `production_line_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `line_plans` RENAME INDEX `uniq_line_plans_team_sequence` TO `uniq_line_plans_line_sequence`');
        // fk_line_plans_production_line re-added below, once `production_lines` exists again.

        // ---- employees (undo) --------------------------------------------------------------
        DB::statement('ALTER TABLE `employees` DROP FOREIGN KEY `fk_employees_team`');
        DB::statement('ALTER TABLE `employees` DROP FOREIGN KEY `fk_employees_base_team`');
        DB::statement('ALTER TABLE `employees` CHANGE `team_id` `production_line_id` BIGINT UNSIGNED DEFAULT NULL');
        DB::statement('ALTER TABLE `employees` CHANGE `base_team_id` `base_line_id` BIGINT UNSIGNED DEFAULT NULL');
        DB::statement('ALTER TABLE `employees` RENAME INDEX `idx_employees_team` TO `idx_employees_line`');
        DB::statement('ALTER TABLE `employees` RENAME INDEX `fk_employees_base_team` TO `fk_employees_base_line`');
        // fk_employees_production_line / fk_employees_base_line re-added below.

        // ---- teams -> production_lines (undo) ------------------------------------------
        DB::statement('ALTER TABLE `teams` DROP FOREIGN KEY `fk_teams_section`');
        DB::statement('ALTER TABLE `teams` DROP FOREIGN KEY `fk_teams_department`');
        DB::statement('ALTER TABLE `teams` DROP FOREIGN KEY `fk_teams_created_by`');
        DB::statement('ALTER TABLE `teams` DROP FOREIGN KEY `fk_teams_updated_by`');
        DB::statement('ALTER TABLE `teams` DROP FOREIGN KEY `fk_teams_factory`');

        DB::statement('ALTER TABLE `teams` CHANGE `section_id` `category_id` BIGINT UNSIGNED NOT NULL');

        DB::statement('RENAME TABLE `teams` TO `production_lines`');

        DB::statement('ALTER TABLE `production_lines` RENAME INDEX `uniq_teams_name` TO `uniq_production_lines_name`');
        DB::statement('ALTER TABLE `production_lines` RENAME INDEX `fk_teams_section` TO `fk_production_lines_category`');
        DB::statement('ALTER TABLE `production_lines` RENAME INDEX `fk_teams_department` TO `fk_production_lines_department`');
        DB::statement('ALTER TABLE `production_lines` RENAME INDEX `fk_teams_created_by` TO `fk_production_lines_created_by`');
        DB::statement('ALTER TABLE `production_lines` RENAME INDEX `fk_teams_updated_by` TO `fk_production_lines_updated_by`');
        DB::statement('ALTER TABLE `production_lines` RENAME INDEX `idx_teams_factory` TO `idx_production_lines_factory`');

        // Targets that already exist again can be re-added now; fk_production_lines_category
        // (-> line_categories) must wait until the sections block below restores that table.
        DB::statement('ALTER TABLE `production_lines` ADD CONSTRAINT `fk_production_lines_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)');
        DB::statement('ALTER TABLE `production_lines` ADD CONSTRAINT `fk_production_lines_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `production_lines` ADD CONSTRAINT `fk_production_lines_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `production_lines` ADD CONSTRAINT `fk_production_lines_factory` FOREIGN KEY (`factory_id`) REFERENCES `factories` (`id`)');

        // ---- sections -> line_categories (undo) ------------------------------------------
        DB::statement('ALTER TABLE `sections` DROP FOREIGN KEY `fk_sections_created_by`');
        DB::statement('ALTER TABLE `sections` DROP FOREIGN KEY `fk_sections_updated_by`');

        DB::statement('RENAME TABLE `sections` TO `line_categories`');

        DB::statement('ALTER TABLE `line_categories` RENAME INDEX `uniq_sections_name` TO `uniq_line_categories_name`');
        DB::statement('ALTER TABLE `line_categories` RENAME INDEX `fk_sections_created_by` TO `fk_line_categories_created_by`');
        DB::statement('ALTER TABLE `line_categories` RENAME INDEX `fk_sections_updated_by` TO `fk_line_categories_updated_by`');

        DB::statement('ALTER TABLE `line_categories` ADD CONSTRAINT `fk_line_categories_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `line_categories` ADD CONSTRAINT `fk_line_categories_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');

        // ---- deferred FKs: now that both line_categories and production_lines exist again ----
        DB::statement('ALTER TABLE `production_lines` ADD CONSTRAINT `fk_production_lines_category` FOREIGN KEY (`category_id`) REFERENCES `line_categories` (`id`)');
        DB::statement('ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_production_line` FOREIGN KEY (`production_line_id`) REFERENCES `production_lines` (`id`)');
        DB::statement('ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_base_line` FOREIGN KEY (`base_line_id`) REFERENCES `production_lines` (`id`)');
        DB::statement('ALTER TABLE `line_plans` ADD CONSTRAINT `fk_line_plans_production_line` FOREIGN KEY (`production_line_id`) REFERENCES `production_lines` (`id`)');
    }
};
