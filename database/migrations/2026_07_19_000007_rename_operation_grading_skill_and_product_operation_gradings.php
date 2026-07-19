<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: doctrine/dbal is not installed, so Schema::rename()/renameColumn() are unavailable —
// raw SQL is used throughout, mirroring the earlier soft_skills/sections/teams renames.
//
// Step 4 (final) of the Operation-family rename: the two junction tables that hang off
// `operation_gradings`/`operations`. MUST run after 2026_07_19_000006 (needs the final
// `operations` table, née operation_gradings, to already exist under that name).
//
// NOTE: `operation_skill` already exists in this dev DB as a stray, empty (0-row) table
// left over from manual testing — migration history says it was created in
// 2026_06_18_090006 and dropped in 2026_06_21_090005 and never recreated by any migration
// since, but the physical table is still there. It is not referenced by any current model
// or migration. We drop it defensively immediately before the RENAME TABLE below so the
// rename doesn't fail with "table already exists"; down() does not recreate it, since per
// the migration history it is not supposed to exist at this point in the schema's timeline.
return new class extends Migration
{
    public function up(): void
    {
        // ---- operation_grading_skill -> operation_skill ----------------------------------
        DB::statement('ALTER TABLE `operation_grading_skill` DROP FOREIGN KEY `fk_operation_grading_skill_grading`');
        DB::statement('ALTER TABLE `operation_grading_skill` DROP FOREIGN KEY `fk_operation_grading_skill_skill`');
        DB::statement('ALTER TABLE `operation_grading_skill` DROP FOREIGN KEY `fk_operation_grading_skill_created_by`');
        DB::statement('ALTER TABLE `operation_grading_skill` DROP FOREIGN KEY `fk_operation_grading_skill_updated_by`');

        DB::statement('ALTER TABLE `operation_grading_skill` CHANGE `operation_grading_id` `operation_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `operation_grading_skill` CHANGE `skill_id` `soft_skill_id` BIGINT UNSIGNED NOT NULL');

        // Defensive cleanup of the stray leftover table — see class-level NOTE above.
        DB::statement('DROP TABLE IF EXISTS `operation_skill`');

        DB::statement('RENAME TABLE `operation_grading_skill` TO `operation_skill`');

        DB::statement('ALTER TABLE `operation_skill` RENAME INDEX `uniq_operation_grading_skill_grading_skill` TO `uniq_operation_skill_operation_skill`');
        DB::statement('ALTER TABLE `operation_skill` RENAME INDEX `fk_operation_grading_skill_skill` TO `fk_operation_skill_skill`');
        DB::statement('ALTER TABLE `operation_skill` RENAME INDEX `fk_operation_grading_skill_created_by` TO `fk_operation_skill_created_by`');
        DB::statement('ALTER TABLE `operation_skill` RENAME INDEX `fk_operation_grading_skill_updated_by` TO `fk_operation_skill_updated_by`');

        DB::statement('ALTER TABLE `operation_skill` ADD CONSTRAINT `fk_operation_skill_operation` FOREIGN KEY (`operation_id`) REFERENCES `operations` (`id`)');
        DB::statement('ALTER TABLE `operation_skill` ADD CONSTRAINT `fk_operation_skill_soft_skill` FOREIGN KEY (`soft_skill_id`) REFERENCES `soft_skills` (`id`)');
        DB::statement('ALTER TABLE `operation_skill` ADD CONSTRAINT `fk_operation_skill_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `operation_skill` ADD CONSTRAINT `fk_operation_skill_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');

        // ---- product_operation_gradings -> product_operations ----------------------------
        DB::statement('ALTER TABLE `product_operation_gradings` DROP FOREIGN KEY `fk_product_op_gradings_grading`');
        DB::statement('ALTER TABLE `product_operation_gradings` DROP FOREIGN KEY `fk_product_op_gradings_product`');
        DB::statement('ALTER TABLE `product_operation_gradings` DROP FOREIGN KEY `fk_product_op_gradings_created_by`');
        DB::statement('ALTER TABLE `product_operation_gradings` DROP FOREIGN KEY `fk_product_op_gradings_updated_by`');

        DB::statement('ALTER TABLE `product_operation_gradings` CHANGE `operation_grading_id` `operation_id` BIGINT UNSIGNED NOT NULL');

        DB::statement('RENAME TABLE `product_operation_gradings` TO `product_operations`');

        DB::statement('ALTER TABLE `product_operations` RENAME INDEX `uniq_product_op_gradings_product_grading` TO `uniq_product_operations_product_operation`');
        DB::statement('ALTER TABLE `product_operations` RENAME INDEX `uniq_product_op_gradings_product_sequence` TO `uniq_product_operations_product_sequence`');
        DB::statement('ALTER TABLE `product_operations` RENAME INDEX `fk_product_op_gradings_grading` TO `fk_product_operations_operation`');
        DB::statement('ALTER TABLE `product_operations` RENAME INDEX `fk_product_op_gradings_created_by` TO `fk_product_operations_created_by`');
        DB::statement('ALTER TABLE `product_operations` RENAME INDEX `fk_product_op_gradings_updated_by` TO `fk_product_operations_updated_by`');

        DB::statement('ALTER TABLE `product_operations` ADD CONSTRAINT `fk_product_operations_operation` FOREIGN KEY (`operation_id`) REFERENCES `operations` (`id`)');
        DB::statement('ALTER TABLE `product_operations` ADD CONSTRAINT `fk_product_operations_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)');
        DB::statement('ALTER TABLE `product_operations` ADD CONSTRAINT `fk_product_operations_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `product_operations` ADD CONSTRAINT `fk_product_operations_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
    }

    public function down(): void
    {
        // ---- product_operations -> product_operation_gradings (undo) ---------------------
        DB::statement('ALTER TABLE `product_operations` DROP FOREIGN KEY `fk_product_operations_operation`');
        DB::statement('ALTER TABLE `product_operations` DROP FOREIGN KEY `fk_product_operations_product`');
        DB::statement('ALTER TABLE `product_operations` DROP FOREIGN KEY `fk_product_operations_created_by`');
        DB::statement('ALTER TABLE `product_operations` DROP FOREIGN KEY `fk_product_operations_updated_by`');

        DB::statement('ALTER TABLE `product_operations` RENAME INDEX `uniq_product_operations_product_operation` TO `uniq_product_op_gradings_product_grading`');
        DB::statement('ALTER TABLE `product_operations` RENAME INDEX `uniq_product_operations_product_sequence` TO `uniq_product_op_gradings_product_sequence`');
        DB::statement('ALTER TABLE `product_operations` RENAME INDEX `fk_product_operations_operation` TO `fk_product_op_gradings_grading`');
        DB::statement('ALTER TABLE `product_operations` RENAME INDEX `fk_product_operations_created_by` TO `fk_product_op_gradings_created_by`');
        DB::statement('ALTER TABLE `product_operations` RENAME INDEX `fk_product_operations_updated_by` TO `fk_product_op_gradings_updated_by`');

        DB::statement('ALTER TABLE `product_operations` CHANGE `operation_id` `operation_grading_id` BIGINT UNSIGNED NOT NULL');

        DB::statement('RENAME TABLE `product_operations` TO `product_operation_gradings`');

        DB::statement('ALTER TABLE `product_operation_gradings` ADD CONSTRAINT `fk_product_op_gradings_grading` FOREIGN KEY (`operation_grading_id`) REFERENCES `operations` (`id`)');
        DB::statement('ALTER TABLE `product_operation_gradings` ADD CONSTRAINT `fk_product_op_gradings_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)');
        DB::statement('ALTER TABLE `product_operation_gradings` ADD CONSTRAINT `fk_product_op_gradings_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `product_operation_gradings` ADD CONSTRAINT `fk_product_op_gradings_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');

        // ---- operation_skill -> operation_grading_skill (undo) ----------------------------
        DB::statement('ALTER TABLE `operation_skill` DROP FOREIGN KEY `fk_operation_skill_operation`');
        DB::statement('ALTER TABLE `operation_skill` DROP FOREIGN KEY `fk_operation_skill_soft_skill`');
        DB::statement('ALTER TABLE `operation_skill` DROP FOREIGN KEY `fk_operation_skill_created_by`');
        DB::statement('ALTER TABLE `operation_skill` DROP FOREIGN KEY `fk_operation_skill_updated_by`');

        DB::statement('ALTER TABLE `operation_skill` RENAME INDEX `uniq_operation_skill_operation_skill` TO `uniq_operation_grading_skill_grading_skill`');
        DB::statement('ALTER TABLE `operation_skill` RENAME INDEX `fk_operation_skill_skill` TO `fk_operation_grading_skill_skill`');
        DB::statement('ALTER TABLE `operation_skill` RENAME INDEX `fk_operation_skill_created_by` TO `fk_operation_grading_skill_created_by`');
        DB::statement('ALTER TABLE `operation_skill` RENAME INDEX `fk_operation_skill_updated_by` TO `fk_operation_grading_skill_updated_by`');

        DB::statement('RENAME TABLE `operation_skill` TO `operation_grading_skill`');

        DB::statement('ALTER TABLE `operation_grading_skill` CHANGE `operation_id` `operation_grading_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `operation_grading_skill` CHANGE `soft_skill_id` `skill_id` BIGINT UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE `operation_grading_skill` ADD CONSTRAINT `fk_operation_grading_skill_grading` FOREIGN KEY (`operation_grading_id`) REFERENCES `operations` (`id`)');
        DB::statement('ALTER TABLE `operation_grading_skill` ADD CONSTRAINT `fk_operation_grading_skill_skill` FOREIGN KEY (`skill_id`) REFERENCES `soft_skills` (`id`)');
        DB::statement('ALTER TABLE `operation_grading_skill` ADD CONSTRAINT `fk_operation_grading_skill_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)');
        DB::statement('ALTER TABLE `operation_grading_skill` ADD CONSTRAINT `fk_operation_grading_skill_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`)');
    }
};
