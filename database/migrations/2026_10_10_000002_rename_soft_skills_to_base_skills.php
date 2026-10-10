<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR: doctrine/dbal is not installed, so Schema::rename()/renameColumn() are unavailable —
// raw SQL is used throughout, mirroring 2026_10_10_000001_rename_products_to_styles.
//
// Business terminology change: "Soft Skill" -> "Base Skill".
//   soft_skills -> base_skills, soft_skill_id -> base_skill_id (operation_skill, time_study_skills),
//   plus every index/FK name that said "soft_skill(s)". Index names without "soft"
//   (e.g. fk_operation_skill_skill, uniq_time_study_skills_study_skill) are left as-is.
return new class extends Migration
{
    /** [table (old name) => [[old FK name, new FK name, column (old), ref table (old)], ...]] */
    private const FOREIGN_KEYS = [
        'soft_skills' => [
            ['fk_soft_skills_created_by', 'fk_base_skills_created_by', 'created_by_id', 'users'],
            ['fk_soft_skills_updated_by', 'fk_base_skills_updated_by', 'updated_by_id', 'users'],
        ],
        'operation_skill' => [
            ['fk_operation_skill_soft_skill', 'fk_operation_skill_base_skill', 'soft_skill_id', 'soft_skills'],
        ],
        'time_study_skills' => [
            ['fk_time_study_skills_skill', 'fk_time_study_skills_skill', 'soft_skill_id', 'soft_skills'],
        ],
    ];

    /** Indexes on soft_skills/base_skills: [old name => new name]. */
    private const INDEXES = [
        'uniq_soft_skills_code' => 'uniq_base_skills_code',
        'uniq_soft_skills_name' => 'uniq_base_skills_name',
        'fk_soft_skills_created_by' => 'fk_base_skills_created_by',
        'fk_soft_skills_updated_by' => 'fk_base_skills_updated_by',
    ];

    /** Tables whose soft_skill_id column becomes base_skill_id (both NOT NULL). */
    private const SKILL_ID_TABLES = ['operation_skill', 'time_study_skills'];

    /** screen_name must match the navigator.php caption (screen_code ie/op-skills is unchanged). */
    private const SCREEN_CODE = 'ie/op-skills';
    private const SCREEN_NAMES = ['Base Operations & Soft Skills', 'Base Operations & Base Skills'];

    public function up(): void
    {
        $this->apply(true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->apply(false);
    }

    private function apply(bool $forward): void
    {
        $skillsTable = $forward ? 'base_skills' : 'soft_skills';
        $table = fn (string $old) => $old === 'soft_skills' ? $skillsTable : $old;
        $col = fn (string $c) => $c === 'soft_skill_id' ? ($forward ? 'base_skill_id' : 'soft_skill_id') : $c;

        // 1. Drop affected FKs (named as they currently exist, on the current table name).
        foreach (self::FOREIGN_KEYS as $old => $fks) {
            $current = $old === 'soft_skills' ? ($forward ? 'soft_skills' : 'base_skills') : $old;
            foreach ($fks as [$oldName, $newName]) {
                DB::statement("ALTER TABLE `{$current}` DROP FOREIGN KEY `" . ($forward ? $oldName : $newName) . '`');
            }
        }

        // 2. Rename the table.
        [$from, $to] = $forward ? ['soft_skills', 'base_skills'] : ['base_skills', 'soft_skills'];
        DB::statement("RENAME TABLE `{$from}` TO `{$to}`");

        // 3. Rename soft_skill_id <-> base_skill_id.
        [$fromCol, $toCol] = $forward ? ['soft_skill_id', 'base_skill_id'] : ['base_skill_id', 'soft_skill_id'];
        foreach (self::SKILL_ID_TABLES as $t) {
            DB::statement("ALTER TABLE `{$t}` CHANGE `{$fromCol}` `{$toCol}` BIGINT UNSIGNED NOT NULL");
        }

        // 4. Rename indexes on the skills table.
        foreach (self::INDEXES as $oldName => $newName) {
            [$fromIdx, $toIdx] = $forward ? [$oldName, $newName] : [$newName, $oldName];
            DB::statement("ALTER TABLE `{$skillsTable}` RENAME INDEX `{$fromIdx}` TO `{$toIdx}`");
        }

        // 5. Re-add FKs under their new (or restored) names.
        foreach (self::FOREIGN_KEYS as $old => $fks) {
            foreach ($fks as [$oldName, $newName, $column, $refTable]) {
                $name = $forward ? $newName : $oldName;
                DB::statement(
                    "ALTER TABLE `{$table($old)}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$col($column)}`) "
                    . "REFERENCES `{$table($refTable)}` (`id`)"
                );
            }
        }

        // 6. Keep the permission screen in sync with the navigator caption.
        [$fromName, $toName] = $forward ? self::SCREEN_NAMES : array_reverse(self::SCREEN_NAMES);
        DB::table('screens')
            ->where('screen_code', self::SCREEN_CODE)
            ->where('screen_name', $fromName)
            ->update(['screen_name' => $toName]);
    }
};
