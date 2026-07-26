<?php

namespace Tests\Unit\Repositories;

use App\BaseOperation;
use App\BaseOperationCategory;
use App\Exceptions\GeneralException;
use App\Http\Repositories\OperationRepository;
use App\MachineCategory;
use App\MachineType;
use App\OperationGrade;
use App\ProductCategory;
use App\ProductGroup;

class OperationRepositoryTest extends RepositoryTestCase
{
    /** @return array{0:int,1:int,2:int,3:int} [base_operation_id, product_category_id, machine_type_id, grade_id] */
    private function makeScope(string $suffix = ''): array
    {
        $unique = strtoupper(substr(md5($suffix.uniqid()), 0, 6));

        $baseOperationCategoryId = BaseOperationCategory::create(['name' => 'Sewing'.$suffix])->id;
        $baseOperationId = BaseOperation::create(['name' => 'Stitch Sleeve'.$suffix, 'code' => 'BO'.$unique, 'base_operation_category_id' => $baseOperationCategoryId])->id;

        $groupId = ProductGroup::create(['name' => 'Knits'.$suffix, 'code' => 'KN'.$unique])->id;
        $categoryId = ProductCategory::create(['name' => 'T-Shirts'.$suffix, 'code' => 'TS'.$unique, 'product_group_id' => $groupId])->id;

        $machineCategoryId = MachineCategory::create(['name' => 'Sewing Machines'.$suffix])->id;
        $machineTypeId = MachineType::create(['name' => 'Single Needle'.$suffix, 'code' => 'MT'.$unique, 'machine_category_id' => $machineCategoryId])->id;

        $gradeId = OperationGrade::create(['name' => 'Grade A'.$suffix, 'code' => 'OG'.$unique])->id;

        return [$baseOperationId, $categoryId, $machineTypeId, $gradeId];
    }

    private function payload(array $scope, string $code, string $desc): array
    {
        [$baseOperationId, $categoryId, $machineTypeId, $gradeId] = $scope;

        return [
            'description' => $desc,
            'code' => $code,
            'base_operation_id' => $baseOperationId,
            'product_category_id' => $categoryId,
            'machine_type_id' => $machineTypeId,
            'grade_id' => $gradeId,
        ];
    }

    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();
        $scope = $this->makeScope();

        $model = OperationRepository::createRec($this->payload($scope, 'OP001', 'Stitch Sleeve - T-Shirts - Single Needle'));

        $this->assertDatabaseHas('operations', ['id' => $model->id, 'code' => 'OP001']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();
        $scope = $this->makeScope();
        $rec = $this->payload($scope, 'OP001', 'Stitch Sleeve - T-Shirts - Single Needle');
        unset($rec['code']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        OperationRepository::createRec($rec);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();
        $scopeOne = $this->makeScope('One');
        $scopeTwo = $this->makeScope('Two');

        OperationRepository::createRec($this->payload($scopeOne, 'OP001', 'Op One'));

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        // Code uniqueness is global, unlike the product_category/base_operation/machine_type
        // combination, so even an otherwise-distinct scope still collides on a shared code.
        OperationRepository::createRec($this->payload($scopeTwo, 'OP001', 'Op Two'));
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();
        $scopeOne = $this->makeScope('One');
        $scopeTwo = $this->makeScope('Two');

        $model = OperationRepository::createRec($this->payload($scopeOne, 'OP001', 'Op One'));
        OperationRepository::createRec($this->payload($scopeTwo, 'OP002', 'Op Two'));

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        $rec = $this->payload($scopeOne, 'OP002', 'Op One');
        $rec['updated_at'] = $model->updated_at;

        OperationRepository::updateRec($model->id, $rec);
    }
}
