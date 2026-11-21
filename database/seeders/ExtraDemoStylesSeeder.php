<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Five extra demo styles on five different silhouettes and customers, each with a bulletin made of
 * that silhouette's existing operations. Mainly so the Styles table has more than one page (20) of rows.
 * Style codes start with "PGDEMO-"; nothing real is edited or deleted.
 *
 * Safe to re-run: removes any prior PGDEMO- styles first, then reinserts.
 * Remove only:   (new \Database\Seeders\ExtraDemoStylesSeeder)->remove()   (e.g. in tinker)
 * Run:           php artisan db:seed --class="Database\Seeders\ExtraDemoStylesSeeder"
 */
class ExtraDemoStylesSeeder extends Seeder
{
    private const CODE_PREFIX = 'PGDEMO-';
    /** The real demo styles live in these factories, so the new styles appear without switching factory. */
    private const FACTORY_IDS = [3, 4];

    /** [name, code suffix, silhouette, customer description, season name, how many of the silhouette's operations] */
    private const STYLES = [
        ['Lululemon Define Jacket', '01', 'Short', 'LULULEMON', 'FA27', 8],
        ['Nike Pro Polo', '02', 'Polo tee', 'NIKE', 'SP27', 10],
        ['Levis 501 Work Shirt', '03', 'Shirt', 'LEVISE & CO', 'SP27', 12],
        ["VS Lace Bralette", '04', 'Bra', "VICTORIA'S SECRET", 'SU27', 9],
        ['Walmart Cozy PJ Top', '05', 'PJ-top', 'WALMART', 'FA27', 8],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->remove();
            $userId = DB::table('users')->orderBy('id')->value('id');

            foreach (self::STYLES as [$name, $suffix, $category, $customer, $season, $opCount]) {
                $categoryId = $this->lookup('product_categories', ['name' => $category], "Product silhouette \"$category\"");
                $customerId = $this->lookup('customers', ['description' => $customer], "Customer \"$customer\"");
                $seasonId = $this->lookup('seasons', ['customer_id' => $customerId, 'name' => $season], "Season \"$season\" of \"$customer\"");
                $styleId = $this->insertStyle($name, self::CODE_PREFIX.$suffix, $categoryId, $customerId, $seasonId, $userId);
                $this->insertSteps($styleId, $categoryId, $opCount, $userId);
            }
        });

        $this->command?->info('Extra demo styles seeded: 5 "PGDEMO-" styles with operation bulletins.');
    }

    /** FK-safe teardown, scoped strictly to styles whose code starts with PGDEMO-. */
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

    private function lookup(string $table, array $where, string $what): int
    {
        $id = DB::table($table)->where($where)->value('id');
        if (!$id) {
            throw new RuntimeException("$what not found; this demo dataset needs it.");
        }

        return (int) $id;
    }

    private function insertStyle(string $name, string $code, int $categoryId, int $customerId, int $seasonId, ?int $userId): int
    {
        $now = now();
        $styleId = DB::table('styles')->insertGetId([
            'name' => $name, 'style_code' => $code, 'style_description' => 'Extra demo style',
            'product_category_id' => $categoryId, 'customer_id' => $customerId, 'season_id' => $seasonId,
            'is_active' => 1, 'created_by_id' => $userId, 'updated_by_id' => $userId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach (self::FACTORY_IDS as $factoryId) {
            DB::table('factory_style')->insert(['factory_id' => $factoryId, 'style_id' => $styleId, 'created_at' => $now, 'updated_at' => $now]);
        }

        return (int) $styleId;
    }

    /** The first $count active operations of the silhouette, in its own sequence order. */
    private function insertSteps(int $styleId, int $categoryId, int $count, ?int $userId): void
    {
        $operationIds = DB::table('operations')->where('product_category_id', $categoryId)->where('is_active', 1)
            ->orderBy('sequence_no')->orderBy('id')->limit($count)->pluck('id');

        $now = now();
        foreach ($operationIds as $index => $operationId) {
            DB::table('style_operations')->insert([
                'style_id' => $styleId, 'operation_id' => $operationId, 'sequence_no' => $index + 1,
                'smv' => null, 'is_active' => 1,
                'created_by_id' => $userId, 'updated_by_id' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
