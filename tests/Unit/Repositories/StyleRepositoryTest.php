<?php

namespace Tests\Unit\Repositories;

use App\Customer;
use App\Exceptions\GeneralException;
use App\Http\Repositories\StyleRepository;
use App\ProductCategory;
use App\ProductGroup;
use App\Season;
use App\Style;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProductFamilies;

class StyleRepositoryTest extends RepositoryTestCase
{
    /** @return array{0:int,1:int,2:int} [product_category_id, customer_id, season_id] */
    private function makeScope(string $suffix = ''): array
    {
        $unique = strtoupper(substr(md5($suffix.uniqid()), 0, 6));
        $groupId = ProductGroup::create(['name' => 'Knits'.$suffix, 'code' => 'KN'.$unique])->id;
        $categoryId = ProductCategory::create(['name' => 'T-Shirts'.$suffix, 'code' => 'TS'.$unique, 'product_family_id' => ProductFamilies::forGroup($groupId)])->id;
        $customerId = Customer::create(['description' => 'Decathlon'.$suffix, 'code' => 'DC'.$unique])->id;
        $seasonId = Season::create(['name' => 'Summer 27', 'code' => 'SU'.$unique, 'customer_id' => $customerId])->id;

        return [$categoryId, $customerId, $seasonId];
    }

    public function testCreatesWithAUniqueStyleCodeCombination()
    {
        $this->actingAsTestUser();
        [$categoryId, $customerId, $seasonId] = $this->makeScope();

        $model = StyleRepository::createRec([
            'name' => 'Style 123',
            'style_code' => 'ST123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);

        $this->assertDatabaseHas('styles', ['id' => $model->id, 'style_code' => 'ST123']);
    }

    public function testExportFiltersCanNarrowStylesToAProductFamily()
    {
        $this->actingAsTestUser();
        [$categoryOne, $customerId, $seasonId] = $this->makeScope('One');
        [$categoryTwo] = $this->makeScope('Two');
        $inFamily = StyleRepository::createRec(['name' => 'In', 'style_code' => 'IN1', 'product_category_id' => $categoryOne, 'customer_id' => $customerId, 'season_id' => $seasonId]);
        StyleRepository::createRec(['name' => 'Out', 'style_code' => 'OUT1', 'product_category_id' => $categoryTwo, 'customer_id' => $customerId, 'season_id' => $seasonId]);
        $familyId = ProductCategory::find($categoryOne)->product_family_id;

        $query = Style::query();
        StyleRepository::applyExportFilters($query, ['product_family_id' => [$familyId]]);

        $this->assertSame([$inFamily->id], $query->pluck('id')->all());
    }

    public function testRejectsAMissingStyleCode()
    {
        $this->actingAsTestUser();
        [$categoryId, $customerId, $seasonId] = $this->makeScope();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/style.code/i');

        StyleRepository::createRec([
            'name' => 'Style 123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);
    }

    public function testRejectsAMissingSeason()
    {
        $this->actingAsTestUser();
        [$categoryId, $customerId] = $this->makeScope();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/season/i');

        StyleRepository::createRec([
            'name' => 'Style 123',
            'style_code' => 'ST123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
        ]);
    }

    public function testRejectsADuplicateStyleCodeForTheSameCategoryCustomerAndSeason()
    {
        $this->actingAsTestUser();
        [$categoryId, $customerId, $seasonId] = $this->makeScope();

        StyleRepository::createRec([
            'name' => 'Style 123',
            'style_code' => 'ST123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/style.code/i');

        StyleRepository::createRec([
            'name' => 'Style 123 Restock',
            'style_code' => 'ST123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);
    }

    /** The uniqueness is scoped to (category, customer, season) together, so the same style_code is fine in a different scope. */
    public function testAllowsTheSameStyleCodeUnderADifferentScope()
    {
        $this->actingAsTestUser();
        [$categoryId, $customerId, $seasonId] = $this->makeScope('One');
        [$categoryTwoId, $customerTwoId, $seasonTwoId] = $this->makeScope('Two');

        StyleRepository::createRec([
            'name' => 'Style 123 A',
            'style_code' => 'ST123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);

        $second = StyleRepository::createRec([
            'name' => 'Style 123 B',
            'style_code' => 'ST123',
            'product_category_id' => $categoryTwoId,
            'customer_id' => $customerTwoId,
            'season_id' => $seasonTwoId,
        ]);

        $this->assertDatabaseHas('styles', ['id' => $second->id, 'style_code' => 'ST123', 'customer_id' => $customerTwoId]);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherStylesStyleCodeInTheSameScope()
    {
        $this->actingAsTestUser();
        [$categoryId, $customerId, $seasonId] = $this->makeScope();

        $model = StyleRepository::createRec([
            'name' => 'Style 123',
            'style_code' => 'ST123',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);
        StyleRepository::createRec([
            'name' => 'Style 456',
            'style_code' => 'ST456',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
        ]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/style.code/i');

        StyleRepository::updateRec($model->id, [
            'name' => 'Style 123',
            'style_code' => 'ST456',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
            'updated_at' => $model->updated_at,
        ]);
    }

    /**
     * A source style whose bulletin has two operations (the second with an SMV override and inactive).
     *
     * @return array{style:Style,rec:array,categoryId:int,customerId:int,seasonId:int,operationIds:int[],machineTypeId:int,baseCategoryId:int,gradeId:int}
     */
    private function sourceStyleWithBulletin(): array
    {
        [$categoryId, $customerId, $seasonId] = $this->makeScope();
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $baseCategoryId = DB::table('base_operation_categories')->insertGetId(['name' => 'Cat '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $gradeId = DB::table('operation_grades')->insertGetId(['name' => 'Grade '.$unique, 'code' => 'OG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $machineCategoryId = DB::table('machine_categories')->insertGetId(['name' => 'MC '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $machineTypeId = DB::table('machine_types')->insertGetId(['name' => 'Machine '.$unique, 'code' => 'MT'.$unique, 'machine_category_id' => $machineCategoryId, 'created_at' => now(), 'updated_at' => now()]);

        $rec = [
            'name' => 'Crew Tee',
            'style_code' => 'CT100',
            'style_description' => 'Basic crew neck',
            'product_category_id' => $categoryId,
            'customer_id' => $customerId,
            'season_id' => $seasonId,
            'colors' => ['Red', 'Blue'],
            'sizes' => ['S', 'M'],
            'planned_efficiency_pct' => 62.5,
            'is_active' => true,
        ];
        $style = StyleRepository::createRec($rec);

        $operationIds = [];
        foreach ([1, 2] as $i) {
            $operationId = $this->makeOperation($categoryId, $machineTypeId, $baseCategoryId, $gradeId, "{$i}{$unique}");
            $operationIds[] = $operationId;
            DB::table('style_operations')->insert([
                'style_id' => $style->id, 'operation_id' => $operationId, 'sequence_no' => $i * 10,
                'smv' => $i === 2 ? 0.75 : null, 'is_active' => $i !== 2, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [
            'style' => $style, 'rec' => $rec, 'categoryId' => $categoryId, 'customerId' => $customerId, 'seasonId' => $seasonId,
            'operationIds' => $operationIds, 'machineTypeId' => $machineTypeId, 'baseCategoryId' => $baseCategoryId, 'gradeId' => $gradeId,
        ];
    }

    private function makeOperation(int $categoryId, int $machineTypeId, int $baseCategoryId, int $gradeId, string $tag): int
    {
        $baseOperationId = DB::table('base_operations')->insertGetId(['name' => "Op {$tag}", 'code' => "BO{$tag}", 'base_operation_category_id' => $baseCategoryId, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('operations')->insertGetId([
            'code' => "OP{$tag}", 'description' => "Operation {$tag}", 'base_operation_id' => $baseOperationId, 'grade_id' => $gradeId,
            'product_category_id' => $categoryId, 'machine_type_id' => $machineTypeId, 'smv' => 0.5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param array $source a sourceStyleWithBulletin() result */
    private function extraOperation(array $source, ?int $categoryId = null): int
    {
        return $this->makeOperation($categoryId ?? $source['categoryId'], $source['machineTypeId'], $source['baseCategoryId'], $source['gradeId'], strtoupper(substr(md5(uniqid('', true)), 0, 8)));
    }

    public function testDuplicatesAStyleWhenTheStyleCodeChanges()
    {
        $this->actingAsTestUser();
        $source = $this->sourceStyleWithBulletin();

        [$copy, $copied] = StyleRepository::duplicateRec($source['style']->id, array_merge($source['rec'], ['name' => 'Crew Tee 2', 'style_code' => 'CT101']));

        $this->assertNotSame($source['style']->id, $copy->id);
        $this->assertDatabaseHas('styles', ['id' => $copy->id, 'name' => 'Crew Tee 2', 'style_code' => 'CT101', 'style_description' => 'Basic crew neck']);
        $this->assertSame(['Red', 'Blue'], $copy->fresh()->colors);
        $this->assertSame(0, $copied);
        $this->assertSame(0, DB::table('style_operations')->where('style_id', $copy->id)->count());
    }

    public function testRejectsADuplicateWhoseUniqueKeyIsUnchanged()
    {
        $this->actingAsTestUser();
        $source = $this->sourceStyleWithBulletin();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/at least one of/i');

        // A different name and only a casing/whitespace change to the code is still the same key.
        StyleRepository::duplicateRec($source['style']->id, array_merge($source['rec'], ['name' => 'Crew Tee 2', 'style_code' => ' ct100 ']));
    }

    public function testAcceptsAChangeToAnyOneOfTheKeyFields()
    {
        $this->actingAsTestUser();
        $source = $this->sourceStyleWithBulletin();
        $otherSeasonId = Season::create(['name' => 'Winter 27', 'code' => 'WI'.strtoupper(substr(md5(uniqid()), 0, 6)), 'customer_id' => $source['customerId']])->id;
        [$otherCategoryId] = $this->makeScope('b');

        [$bySeason] = StyleRepository::duplicateRec($source['style']->id, array_merge($source['rec'], ['name' => 'Copy by season', 'season_id' => $otherSeasonId]));
        [$byCategory] = StyleRepository::duplicateRec($source['style']->id, array_merge($source['rec'], ['name' => 'Copy by category', 'product_category_id' => $otherCategoryId]));

        $this->assertSame($otherSeasonId, (int) $bySeason->season_id);
        $this->assertSame($otherCategoryId, (int) $byCategory->product_category_id);
    }

    public function testStillRequiresAnUnusedName()
    {
        $this->actingAsTestUser();
        $source = $this->sourceStyleWithBulletin();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/name/i');

        StyleRepository::duplicateRec($source['style']->id, array_merge($source['rec'], ['style_code' => 'CT101']));
    }

    public function testRejectsACopyThatClashesWithAnotherStylesKey()
    {
        $this->actingAsTestUser();
        $source = $this->sourceStyleWithBulletin();
        StyleRepository::createRec(array_merge($source['rec'], ['name' => 'Taken', 'style_code' => 'CT101']));

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/style.code/i');

        StyleRepository::duplicateRec($source['style']->id, array_merge($source['rec'], ['name' => 'Crew Tee 2', 'style_code' => 'CT101']));
    }

    public function testCopiesTheChosenOperationsInOrderRenumberedFromOne()
    {
        $this->actingAsTestUser();
        $source = $this->sourceStyleWithBulletin();
        [$first, $second] = $source['operationIds'];

        [$copy, $copied] = StyleRepository::duplicateRec(
            $source['style']->id,
            array_merge($source['rec'], ['name' => 'Crew Tee 2', 'style_code' => 'CT101']),
            [
                ['operation_id' => $second, 'smv' => 0.75, 'is_active' => false],
                ['operation_id' => $first, 'smv' => null, 'is_active' => true],
            ]
        );

        $this->assertSame(2, $copied);
        $rows = DB::table('style_operations')->where('style_id', $copy->id)->orderBy('sequence_no')->get();
        $this->assertSame([$second, $first], $rows->pluck('operation_id')->map(fn ($n) => (int) $n)->all());
        $this->assertSame([1, 2], $rows->pluck('sequence_no')->map(fn ($n) => (int) $n)->all());
        $this->assertSame(['0.7500', null], $rows->pluck('smv')->all());
        $this->assertSame([0, 1], $rows->pluck('is_active')->map(fn ($n) => (int) $n)->all());
        $this->assertSame(2, DB::table('style_operations')->where('style_id', $source['style']->id)->count(), 'the source bulletin is untouched');
    }

    public function testLeavesOutOperationsTheUserUnchecked()
    {
        $this->actingAsTestUser();
        $source = $this->sourceStyleWithBulletin();

        [$copy, $copied] = StyleRepository::duplicateRec(
            $source['style']->id,
            array_merge($source['rec'], ['name' => 'Crew Tee 2', 'style_code' => 'CT101']),
            [['operation_id' => $source['operationIds'][1]]]
        );

        $this->assertSame(1, $copied);
        $row = DB::table('style_operations')->where('style_id', $copy->id)->first();
        $this->assertSame($source['operationIds'][1], (int) $row->operation_id);
        $this->assertSame(1, (int) $row->sequence_no, 'renumbered from 1, not left at the original position');
    }

    public function testAddsAnOperationFromTheMasterThatTheOriginalDidNotHave()
    {
        $this->actingAsTestUser();
        $source = $this->sourceStyleWithBulletin();
        $extra = $this->extraOperation($source);

        [$copy, $copied] = StyleRepository::duplicateRec(
            $source['style']->id,
            array_merge($source['rec'], ['name' => 'Crew Tee 2', 'style_code' => 'CT101']),
            [['operation_id' => $source['operationIds'][0]], ['operation_id' => $extra]]
        );

        $this->assertSame(2, $copied);
        $this->assertSame(
            [$source['operationIds'][0], $extra],
            DB::table('style_operations')->where('style_id', $copy->id)->orderBy('sequence_no')->pluck('operation_id')->map(fn ($n) => (int) $n)->all()
        );
    }

    public function testAllowsABulletinOfTheNewSilhouettesOperationsWhenTheSilhouetteChanges()
    {
        $this->actingAsTestUser();
        $source = $this->sourceStyleWithBulletin();
        [$otherCategoryId] = $this->makeScope('d');
        $otherOperation = $this->extraOperation($source, $otherCategoryId);

        [$copy, $copied] = StyleRepository::duplicateRec(
            $source['style']->id,
            array_merge($source['rec'], ['name' => 'Crew Tee 2', 'product_category_id' => $otherCategoryId]),
            [['operation_id' => $otherOperation]]
        );

        $this->assertSame(1, $copied);
        $this->assertDatabaseHas('style_operations', ['style_id' => $copy->id, 'operation_id' => $otherOperation, 'sequence_no' => 1]);
    }

    public function testRefusesOperationsOfAnotherSilhouetteAndCreatesNothing()
    {
        $this->actingAsTestUser();
        $source = $this->sourceStyleWithBulletin();
        [$otherCategoryId] = $this->makeScope('c');
        $before = Style::count();

        try {
            // The original's operations belong to its own silhouette, not the one the copy moves to.
            StyleRepository::duplicateRec(
                $source['style']->id,
                array_merge($source['rec'], ['name' => 'Crew Tee 2', 'product_category_id' => $otherCategoryId]),
                [['operation_id' => $source['operationIds'][0]]]
            );
            $this->fail('Expected the copy to be refused.');
        } catch (GeneralException $e) {
            $this->assertMatchesRegularExpression('/silhouette/i', $e->getMessage());
        }

        $this->assertSame($before, Style::count());
    }

    public function testRefusesTheSameOperationTwiceAndUnknownOperations()
    {
        $this->actingAsTestUser();
        $source = $this->sourceStyleWithBulletin();
        $rec = array_merge($source['rec'], ['name' => 'Crew Tee 2', 'style_code' => 'CT101']);
        $before = Style::count();

        foreach ([
            [['operation_id' => $source['operationIds'][0]], ['operation_id' => $source['operationIds'][0]]],
            [['operation_id' => 999999]],
            [['operation_id' => $source['operationIds'][0], 'smv' => -1]],
        ] as $operations) {
            try {
                StyleRepository::duplicateRec($source['style']->id, $rec, $operations);
                $this->fail('Expected the copy to be refused.');
            } catch (GeneralException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        $this->assertSame($before, Style::count());
    }
}
