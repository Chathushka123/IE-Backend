<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Test fixture helper: a product category needs a product family (group -> family -> category),
 * so tests that only care about the category create a throwaway family under their group here.
 */
class ProductFamilies
{
    /** Inserts a uniquely named family under the given product group and returns its id. */
    public static function forGroup(int $productGroupId): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 10));

        return DB::table('product_families')->insertGetId([
            'name' => 'Family '.$unique,
            'code' => 'PF'.$unique,
            'product_group_id' => $productGroupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
