<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: doctrine/dbal is not installed, so Schema::rename()/renameColumn() are unavailable —
// raw SQL is used throughout, mirroring 2026_07_19_000003_rename_production_line_and_line_category_tables.
//
// line_plans -> team_plans (all columns already use team_id/etc. from that earlier migration;
// only the table name and its indexes/constraints still said "line_plans").
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `line_plans` DROP FOREIGN KEY `fk_line_plans_team`');
        DB::statement('ALTER TABLE `line_plans` DROP FOREIGN KEY `fk_line_plans_product`');
        DB::statement('ALTER TABLE `line_plans` DROP FOREIGN KEY `fk_line_plans_created_by`');
        DB::statement('ALTER TABLE `line_plans` DROP FOREIGN KEY `fk_line_plans_updated_by`');

        DB::statement('RENAME TABLE `line_plans` TO `team_plans`');

        DB::statement('ALTER TABLE `team_plans` RENAME INDEX `uniq_line_plans_team_sequence` TO `uniq_team_plans_team_sequence`');
        DB::statement('ALTER TABLE `team_plans` RENAME INDEX `idx_line_plans_product` TO `idx_team_plans_product`');
        DB::statement('ALTER TABLE `team_plans` RENAME INDEX `idx_line_plans_status` TO `idx_team_plans_status`');
        // No `fk_line_plans_team`/`fk_line_plans_product` index to rename — those FKs were
        // always backed by uniq_line_plans_team_sequence / idx_line_plans_product respectively
        // (both pre-existed the foreign() calls in the original migration), so MySQL never
        // created separate single-column indexes under the FK constraint's own name for them.
        DB::statement('ALTER TABLE `team_plans` RENAME INDEX `fk_line_plans_created_by` TO `fk_team_plans_created_by`');
        DB::statement('ALTER TABLE `team_plans` RENAME INDEX `fk_line_plans_updated_by` TO `fk_team_plans_updated_by`');

        DB::statement('ALTER TABLE `team_plans` ADD CONSTRAINT `fk_team_plans_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)');
        DB::statement('ALTER TABLE `team_plans` ADD CONSTRAINT `fk_team_plans_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)');
        DB::statement('ALTER TABLE `team_plans` ADD CONSTRAINT `fk_team_plans_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `team_plans` ADD CONSTRAINT `fk_team_plans_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `team_plans` DROP FOREIGN KEY `fk_team_plans_team`');
        DB::statement('ALTER TABLE `team_plans` DROP FOREIGN KEY `fk_team_plans_product`');
        DB::statement('ALTER TABLE `team_plans` DROP FOREIGN KEY `fk_team_plans_created_by`');
        DB::statement('ALTER TABLE `team_plans` DROP FOREIGN KEY `fk_team_plans_updated_by`');

        DB::statement('ALTER TABLE `team_plans` RENAME INDEX `uniq_team_plans_team_sequence` TO `uniq_line_plans_team_sequence`');
        DB::statement('ALTER TABLE `team_plans` RENAME INDEX `idx_team_plans_product` TO `idx_line_plans_product`');
        DB::statement('ALTER TABLE `team_plans` RENAME INDEX `idx_team_plans_status` TO `idx_line_plans_status`');
        DB::statement('ALTER TABLE `team_plans` RENAME INDEX `fk_team_plans_created_by` TO `fk_line_plans_created_by`');
        DB::statement('ALTER TABLE `team_plans` RENAME INDEX `fk_team_plans_updated_by` TO `fk_line_plans_updated_by`');

        DB::statement('RENAME TABLE `team_plans` TO `line_plans`');

        DB::statement('ALTER TABLE `line_plans` ADD CONSTRAINT `fk_line_plans_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)');
        DB::statement('ALTER TABLE `line_plans` ADD CONSTRAINT `fk_line_plans_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)');
        DB::statement('ALTER TABLE `line_plans` ADD CONSTRAINT `fk_line_plans_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `line_plans` ADD CONSTRAINT `fk_line_plans_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
    }
};
