<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Small, hand-verifiable dataset for the "Copy Operations" dialog on a style's operation bulletin
 * (StyleOperationRepository::copyFromStyle). Everything is five demo styles whose style code starts
 * with "CPYDEMO-" on the existing "Tee shirt" silhouette; it reuses that silhouette's existing
 * operations and never edits or deletes a real style, operation or customer.
 *
 * Safe to re-run: removes any prior CPYDEMO- styles first, then reinserts.
 * Remove only:   (new \Database\Seeders\StyleOperationCopyDatasetSeeder)->remove()   (e.g. in tinker)
 * Run:           php artisan db:seed --class="Database\Seeders\StyleOperationCopyDatasetSeeder"
 *
 * ── Scenario reference ─────────────────────────────────────────────────────
 * Tee shirt operations, in the silhouette's own order, are called O1..O12 below.
 * Open "CPY Demo Target Tee" in Styles > Operation bulletin and press "Copy Operations".
 *
 *   CPY Demo Target Tee        (the target, "B")  O1 O2 O3
 *
 *   CPY Demo Source Full       O1..O12. O5 has an SMV override, O7 is inactive.
 *                              -> offers 9 (O4..O12): O5 shows the amber "uses x" note, O7 an Inactive badge.
 *   CPY Demo Source Partial    A's order O2 O6 O3 O10 O8 O11
 *                              -> offers 4 in A's order: O6 O10 O8 O11 (O2 and O3 hidden, "2 already in").
 *                                 Copying all four should append them as steps 4..7 in that order.
 *   CPY Demo Source Same       O1 O2 O3 (what the target has)
 *                              -> "already has every operation" empty state.
 *   CPY Demo Source Empty      no operations -> "has no operations yet" empty state.
 *
 * The three real Tee shirt styles (Levis Classic Crew Tee, Nike Dri-FIT Tee, Walmart Everyday Tee)
 * also appear in the list and each offers 9. Styles of other silhouettes must NOT appear.
 * The sources span four customers, so the customer filter and the search box have something to do.
 */
class StyleOperationCopyDatasetSeeder extends Seeder
{
    private const CODE_PREFIX = 'CPYDEMO-';
    private const CATEGORY_NAME = 'Tee shirt';
    /** The real demo styles live in these factories, so the demo styles appear without switching factory. */
    private const FACTORY_IDS = [3, 4];

    public function run(): void
    {
        DB::transaction(function () {
            $this->remove();

            $categoryId = $this->categoryId();
            $ops = $this->operationsOf($categoryId);
            $userId = DB::table('users')->orderBy('id')->value('id');

            // [name, code suffix, customer description, season name, steps]; a step is an operation
            // number (1-based, into $ops) or [number, smv override, is_active].
            $styles = [
                ['CPY Demo Target Tee', 'B', 'LEVISE & CO', 'SP27', [1, 2, 3]],
                ['CPY Demo Source Full', 'A1', 'WALMART', 'FA27', [1, 2, 3, 4, [5, 'x1.5', true], 6, [7, null, false], 8, 9, 10, 11, 12]],
                ['CPY Demo Source Partial', 'A2', 'NIKE', 'SP27', [2, 6, 3, 10, 8, 11]],
                ['CPY Demo Source Same', 'A3', 'LEVISE & CO', 'SP27', [1, 2, 3]],
                ['CPY Demo Source Empty', 'A4', 'LULULEMON', 'WI27', []],
            ];

            foreach ($styles as [$name, $suffix, $customer, $season, $steps]) {
                $styleId = $this->insertStyle($name, self::CODE_PREFIX.$suffix, $categoryId, $customer, $season, $userId);
                $this->insertSteps($styleId, $steps, $ops, $userId);
            }
        });

        $this->command?->info('Copy Operations demo dataset seeded: 5 "CPY Demo" styles on the Tee shirt silhouette.');
    }

    /** FK-safe teardown, scoped strictly to styles whose code starts with CPYDEMO-. */
    public function remove(): void
    {
        $ids = DB::table('styles')->where('style_code', 'like', self::CODE_PREFIX.'%')->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        DB::table('style_operations')->whereIn('style_id', $ids)->delete();
        DB::table('factory_style')->whereIn('style_id', $ids)->delete();
        DB::table('styles')->whereIn('id', $ids)->delete();
    }

    private function categoryId(): int
    {
        $id = DB::table('product_categories')->where('name', self::CATEGORY_NAME)->value('id');
        if (!$id) {
            throw new RuntimeException('Product silhouette "'.self::CATEGORY_NAME.'" not found; the demo dataset needs it.');
        }

        return (int) $id;
    }

    /** @return array<int,object> operations of the silhouette keyed 1..n in its own sequence order */
    private function operationsOf(int $categoryId): array
    {
        $rows = DB::table('operations')->where('product_category_id', $categoryId)->where('is_active', 1)
            ->orderBy('sequence_no')->orderBy('id')->get(['id', 'smv'])->all();
        if (count($rows) < 12) {
            throw new RuntimeException('The demo dataset needs 12 active "'.self::CATEGORY_NAME.'" operations, found '.count($rows).'.');
        }

        return array_combine(range(1, count($rows)), $rows);
    }

    private function insertStyle(string $name, string $code, int $categoryId, string $customer, string $season, ?int $userId): int
    {
        $customerId = DB::table('customers')->where('description', $customer)->value('id');
        $seasonId = $customerId ? DB::table('seasons')->where('customer_id', $customerId)->where('name', $season)->value('id') : null;
        if (!$customerId || !$seasonId) {
            throw new RuntimeException("Customer \"$customer\" / season \"$season\" not found; the demo dataset needs them.");
        }

        $now = now();
        $styleId = DB::table('styles')->insertGetId([
            'name' => $name, 'style_code' => $code, 'style_description' => 'Demo style for the Copy Operations dialog',
            'product_category_id' => $categoryId, 'customer_id' => $customerId, 'season_id' => $seasonId,
            'is_active' => 1, 'created_by_id' => $userId, 'updated_by_id' => $userId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach (self::FACTORY_IDS as $factoryId) {
            DB::table('factory_style')->insert(['factory_id' => $factoryId, 'style_id' => $styleId, 'created_at' => $now, 'updated_at' => $now]);
        }

        return (int) $styleId;
    }

    private function insertSteps(int $styleId, array $steps, array $ops, ?int $userId): void
    {
        $now = now();
        foreach (array_values($steps) as $index => $step) {
            [$number, $override, $active] = is_array($step) ? $step : [$step, null, true];
            // 'x1.5' = 1.5 x the operation master's SMV, so the override differs visibly from it.
            $smv = is_string($override) ? round($ops[$number]->smv * (float) substr($override, 1), 4) : $override;
            DB::table('style_operations')->insert([
                'style_id' => $styleId, 'operation_id' => $ops[$number]->id, 'sequence_no' => $index + 1,
                'smv' => $smv, 'is_active' => $active ? 1 : 0,
                'created_by_id' => $userId, 'updated_by_id' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
