<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: doctrine/dbal is not installed, so Schema::rename()/renameColumn() are unavailable —
// raw SQL is used throughout, mirroring the earlier soft_skills/sections/teams renames.
//
// Step 2 of the Operation-family rename (Operation -> BaseOperation). MUST run after
// 2026_07_19_000004 (needs `base_operation_categories` to already exist) and BEFORE
// 2026_07_19_000006 (which needs the name `operations` to be vacated so the *next*
// table, operation_gradings, can take it over).
//
// NOTE on down(): migrate:rollback runs these 4 files' down() in reverse timestamp
// order (0007, 0006, 0005, 0004), so when THIS down() runs, 2026_07_19_000004's down()
// has NOT run yet — the category table is still named `base_operation_categories`, not
// yet renamed back to `operation_categories`. The FK re-add below must target the table
// name that is actually live at that point in the chain (`base_operation_categories`).
// Once 000004's down() later renames it back to `operation_categories`, InnoDB
// automatically repoints this FK to follow — no further action needed here.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `operations` DROP FOREIGN KEY `fk_operations_operation_category`');
        DB::statement('ALTER TABLE `operations` DROP FOREIGN KEY `fk_operations_created_by`');
        DB::statement('ALTER TABLE `operations` DROP FOREIGN KEY `fk_operations_updated_by`');

        DB::statement('ALTER TABLE `operations` CHANGE `operation_category_id` `base_operation_category_id` BIGINT UNSIGNED NOT NULL');

        DB::statement('RENAME TABLE `operations` TO `base_operations`');

        DB::statement('ALTER TABLE `base_operations` RENAME INDEX `uniq_operations_name` TO `uniq_base_operations_name`');
        DB::statement('ALTER TABLE `base_operations` RENAME INDEX `uniq_operations_code` TO `uniq_base_operations_code`');
        DB::statement('ALTER TABLE `base_operations` RENAME INDEX `fk_operations_operation_category` TO `fk_base_operations_base_operation_category`');
        DB::statement('ALTER TABLE `base_operations` RENAME INDEX `fk_operations_created_by` TO `fk_base_operations_created_by`');
        DB::statement('ALTER TABLE `base_operations` RENAME INDEX `fk_operations_updated_by` TO `fk_base_operations_updated_by`');

        DB::statement('ALTER TABLE `base_operations` ADD CONSTRAINT `fk_base_operations_base_operation_category` FOREIGN KEY (`base_operation_category_id`) REFERENCES `base_operation_categories` (`id`)');
        DB::statement('ALTER TABLE `base_operations` ADD CONSTRAINT `fk_base_operations_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `base_operations` ADD CONSTRAINT `fk_base_operations_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `base_operations` DROP FOREIGN KEY `fk_base_operations_base_operation_category`');
        DB::statement('ALTER TABLE `base_operations` DROP FOREIGN KEY `fk_base_operations_created_by`');
        DB::statement('ALTER TABLE `base_operations` DROP FOREIGN KEY `fk_base_operations_updated_by`');

        DB::statement('ALTER TABLE `base_operations` RENAME INDEX `uniq_base_operations_name` TO `uniq_operations_name`');
        DB::statement('ALTER TABLE `base_operations` RENAME INDEX `uniq_base_operations_code` TO `uniq_operations_code`');
        DB::statement('ALTER TABLE `base_operations` RENAME INDEX `fk_base_operations_base_operation_category` TO `fk_operations_operation_category`');
        DB::statement('ALTER TABLE `base_operations` RENAME INDEX `fk_base_operations_created_by` TO `fk_operations_created_by`');
        DB::statement('ALTER TABLE `base_operations` RENAME INDEX `fk_base_operations_updated_by` TO `fk_operations_updated_by`');

        DB::statement('ALTER TABLE `base_operations` CHANGE `base_operation_category_id` `operation_category_id` BIGINT UNSIGNED NOT NULL');

        DB::statement('RENAME TABLE `base_operations` TO `operations`');

        // Target is `base_operation_categories` here, not yet `operation_categories` — see NOTE above.
        DB::statement('ALTER TABLE `operations` ADD CONSTRAINT `fk_operations_operation_category` FOREIGN KEY (`operation_category_id`) REFERENCES `base_operation_categories` (`id`)');
        DB::statement('ALTER TABLE `operations` ADD CONSTRAINT `fk_operations_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `operations` ADD CONSTRAINT `fk_operations_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
    }
};
