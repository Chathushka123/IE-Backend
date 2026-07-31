<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: doctrine/dbal is not installed, so Schema::rename()/renameColumn() are unavailable —
// raw SQL is used throughout, mirroring the earlier soft_skills/sections/teams renames.
//
// Step 3 of the Operation-family rename (OperationGrading -> Operation). MUST run after
// 2026_07_19_000005 (needs `base_operations` to already exist, and needs the name
// `operations` to already be vacated by that migration) and BEFORE
// 2026_07_19_000007 (the junction tables reference this renamed `operations` table).
//
// NOTE on down(): migrate:rollback runs these 4 files' down() in reverse timestamp order
// (0007, 0006, 0005, 0004). When THIS down() runs, 2026_07_19_000005's down() has NOT run
// yet — the base-operation table is still named `base_operations`, not yet renamed back to
// `operations`. The FK re-add below must target `base_operations` (the name actually live
// at that point). Once 000005's down() later renames it back to `operations`, InnoDB
// automatically repoints this FK to follow — no further action needed here.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `operation_gradings` DROP FOREIGN KEY `fk_operation_gradings_operation`');
        DB::statement('ALTER TABLE `operation_gradings` DROP FOREIGN KEY `fk_operation_gradings_product_category`');
        DB::statement('ALTER TABLE `operation_gradings` DROP FOREIGN KEY `fk_operation_gradings_machine_type`');
        DB::statement('ALTER TABLE `operation_gradings` DROP FOREIGN KEY `fk_operation_gradings_grade`');
        DB::statement('ALTER TABLE `operation_gradings` DROP FOREIGN KEY `fk_operation_gradings_created_by`');
        DB::statement('ALTER TABLE `operation_gradings` DROP FOREIGN KEY `fk_operation_gradings_updated_by`');

        DB::statement('ALTER TABLE `operation_gradings` CHANGE `operation_id` `base_operation_id` BIGINT UNSIGNED NOT NULL');

        DB::statement('RENAME TABLE `operation_gradings` TO `operations`');

        DB::statement('ALTER TABLE `operations` RENAME INDEX `uniq_operation_gradings_operation_product_category_machine` TO `uniq_operations_base_operation_product_category_machine`');
        DB::statement('ALTER TABLE `operations` RENAME INDEX `uniq_operation_gradings_product_category_sequence` TO `uniq_operations_product_category_sequence`');
        DB::statement('ALTER TABLE `operations` RENAME INDEX `uniq_operation_gradings_code` TO `uniq_operations_code`');
        DB::statement('ALTER TABLE `operations` RENAME INDEX `fk_operation_gradings_grade` TO `fk_operations_grade`');
        DB::statement('ALTER TABLE `operations` RENAME INDEX `fk_operation_gradings_created_by` TO `fk_operations_created_by`');
        DB::statement('ALTER TABLE `operations` RENAME INDEX `fk_operation_gradings_updated_by` TO `fk_operations_updated_by`');
        DB::statement('ALTER TABLE `operations` RENAME INDEX `fk_operation_gradings_machine_type` TO `fk_operations_machine_type`');

        DB::statement('ALTER TABLE `operations` ADD CONSTRAINT `fk_operations_base_operation` FOREIGN KEY (`base_operation_id`) REFERENCES `base_operations` (`id`)');
        DB::statement('ALTER TABLE `operations` ADD CONSTRAINT `fk_operations_product_category` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`)');
        DB::statement('ALTER TABLE `operations` ADD CONSTRAINT `fk_operations_machine_type` FOREIGN KEY (`machine_type_id`) REFERENCES `machine_types` (`id`)');
        DB::statement('ALTER TABLE `operations` ADD CONSTRAINT `fk_operations_grade` FOREIGN KEY (`grade_id`) REFERENCES `operation_grades` (`id`)');
        DB::statement('ALTER TABLE `operations` ADD CONSTRAINT `fk_operations_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `operations` ADD CONSTRAINT `fk_operations_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `operations` DROP FOREIGN KEY `fk_operations_base_operation`');
        DB::statement('ALTER TABLE `operations` DROP FOREIGN KEY `fk_operations_product_category`');
        DB::statement('ALTER TABLE `operations` DROP FOREIGN KEY `fk_operations_machine_type`');
        DB::statement('ALTER TABLE `operations` DROP FOREIGN KEY `fk_operations_grade`');
        DB::statement('ALTER TABLE `operations` DROP FOREIGN KEY `fk_operations_created_by`');
        DB::statement('ALTER TABLE `operations` DROP FOREIGN KEY `fk_operations_updated_by`');

        DB::statement('ALTER TABLE `operations` RENAME INDEX `uniq_operations_base_operation_product_category_machine` TO `uniq_operation_gradings_operation_product_category_machine`');
        DB::statement('ALTER TABLE `operations` RENAME INDEX `uniq_operations_product_category_sequence` TO `uniq_operation_gradings_product_category_sequence`');
        DB::statement('ALTER TABLE `operations` RENAME INDEX `uniq_operations_code` TO `uniq_operation_gradings_code`');
        DB::statement('ALTER TABLE `operations` RENAME INDEX `fk_operations_grade` TO `fk_operation_gradings_grade`');
        DB::statement('ALTER TABLE `operations` RENAME INDEX `fk_operations_created_by` TO `fk_operation_gradings_created_by`');
        DB::statement('ALTER TABLE `operations` RENAME INDEX `fk_operations_updated_by` TO `fk_operation_gradings_updated_by`');
        DB::statement('ALTER TABLE `operations` RENAME INDEX `fk_operations_machine_type` TO `fk_operation_gradings_machine_type`');

        DB::statement('ALTER TABLE `operations` CHANGE `base_operation_id` `operation_id` BIGINT UNSIGNED NOT NULL');

        DB::statement('RENAME TABLE `operations` TO `operation_gradings`');

        // Target is `base_operations` here, not yet `operations` — see NOTE above.
        DB::statement('ALTER TABLE `operation_gradings` ADD CONSTRAINT `fk_operation_gradings_operation` FOREIGN KEY (`operation_id`) REFERENCES `base_operations` (`id`)');
        DB::statement('ALTER TABLE `operation_gradings` ADD CONSTRAINT `fk_operation_gradings_product_category` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`)');
        DB::statement('ALTER TABLE `operation_gradings` ADD CONSTRAINT `fk_operation_gradings_machine_type` FOREIGN KEY (`machine_type_id`) REFERENCES `machine_types` (`id`)');
        DB::statement('ALTER TABLE `operation_gradings` ADD CONSTRAINT `fk_operation_gradings_grade` FOREIGN KEY (`grade_id`) REFERENCES `operation_grades` (`id`)');
        DB::statement('ALTER TABLE `operation_gradings` ADD CONSTRAINT `fk_operation_gradings_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `operation_gradings` ADD CONSTRAINT `fk_operation_gradings_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
    }
};
