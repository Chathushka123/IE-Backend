<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\SkillMatrixRepository;
use App\Http\Repositories\TimeStudyReviewRepository as Review;
use App\Support\FactoryContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class TimeStudyReviewRepositoryTest extends RepositoryTestCase
{
    // ---------------------------------------------------------------
    // approving and rejecting
    // ---------------------------------------------------------------

    public function testApprovingRecordsWhoWhenAndWhy()
    {
        $reviewer = $this->actingAsTestUser();
        $studyId = $this->makeStudy();

        $study = Review::decide($studyId, 'approved', '  Looks right.  ');

        $row = DB::table('time_studies')->where('id', $studyId)->first();
        $this->assertSame('approved', $study->status);
        $this->assertSame('approved', $row->status);
        $this->assertEquals($reviewer->id, $row->reviewed_by_id);
        $this->assertNotNull($row->reviewed_at);
        $this->assertSame('Looks right.', $row->review_note);
    }

    public function testApprovingNeedsNoNote()
    {
        $this->actingAsTestUser();
        $studyId = $this->makeStudy();

        Review::decide($studyId, 'approved', null);

        $this->assertNull(DB::table('time_studies')->where('id', $studyId)->value('review_note'));
    }

    public function testRejectingRecordsTheReason()
    {
        $this->actingAsTestUser();
        $studyId = $this->makeStudy();

        Review::decide($studyId, 'rejected', 'Operator was on the first day.');

        $row = DB::table('time_studies')->where('id', $studyId)->first();
        $this->assertSame('rejected', $row->status);
        $this->assertSame('Operator was on the first day.', $row->review_note);
    }

    /** @dataProvider missingReasons */
    public function testRejectingNeedsAReasonAndChangesNothingWithout(?string $note)
    {
        $this->actingAsTestUser();
        $studyId = $this->makeStudy();

        try {
            Review::decide($studyId, 'rejected', $note);
            $this->fail('Expected the rejection to be refused.');
        } catch (GeneralException $e) {
            $this->assertStringContainsString('Say why', $e->getMessage());
        }

        $this->assertSame('needs_review', DB::table('time_studies')->where('id', $studyId)->value('status'));
    }

    public static function missingReasons(): array
    {
        return ['null' => [null], 'empty' => [''], 'only spaces' => ['   ']];
    }

    /** @dataProvider badDecisions */
    public function testOnlyApproveOrRejectIsAccepted(?string $decision)
    {
        $this->actingAsTestUser();
        $studyId = $this->makeStudy();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('approve or reject');

        Review::decide($studyId, $decision, 'x');
    }

    public static function badDecisions(): array
    {
        return ['null' => [null], 'empty' => [''], 'pending' => ['needs_review'], 'nonsense' => ['maybe']];
    }

    public function testAVeryLongNoteIsRefused()
    {
        $this->actingAsTestUser();
        $studyId = $this->makeStudy();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('at most 1000');

        Review::decide($studyId, 'approved', str_repeat('x', 1001));
    }

    // ---------------------------------------------------------------
    // decisions are final
    // ---------------------------------------------------------------

    public function testAnApprovedStudyCannotBeDecidedAgain()
    {
        $this->actingAsTestUser();
        $studyId = $this->makeStudy();
        Review::decide($studyId, 'approved', null);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('already been approved');

        Review::decide($studyId, 'rejected', 'Changed my mind.');
    }

    public function testARejectedStudyStaysRejected()
    {
        $this->actingAsTestUser();
        $studyId = $this->makeStudy();
        Review::decide($studyId, 'rejected', 'Bad data.');

        try {
            Review::decide($studyId, 'approved', null);
            $this->fail('Expected the decision to be final.');
        } catch (GeneralException $e) {
            $this->assertStringContainsString('already been rejected', $e->getMessage());
        }

        $this->assertSame('rejected', DB::table('time_studies')->where('id', $studyId)->value('status'));
    }

    public function testAStudyThatWasApprovedAutomaticallyHasNothingToReview()
    {
        $this->actingAsTestUser();
        $studyId = $this->makeStudy(['status' => 'approved']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('already been approved');

        Review::decide($studyId, 'rejected', 'Too late.');
    }

    public function testAnUnknownStudyIsNotFound()
    {
        $this->actingAsTestUser();

        $this->expectException(ModelNotFoundException::class);

        Review::decide(999999, 'approved', null);
    }

    public function testAReviewerCannotReachAStudyFromAnotherFactory()
    {
        $this->actingAsTestUser();
        $mine = $this->makeFactory();
        $studyId = $this->makeStudy([], $this->makeFactory());
        FactoryContext::set([$mine]);

        $this->expectException(ModelNotFoundException::class);

        Review::decide($studyId, 'approved', null);
    }

    // ---------------------------------------------------------------
    // what approval means for the skill matrix
    // ---------------------------------------------------------------

    public function testAnApprovedStudyOfAMatrixTemplateReachesTheSkillMatrixAndARejectedOneDoesNot()
    {
        $this->actingAsTestUser();
        $factoryId = $this->makeFactory();
        $operationId = $this->makeOperation();
        $employeeId = $this->makeEmployee($factoryId);
        $toApprove = $this->makeStudy(['efficiency_pct' => 80], $factoryId, $operationId, $employeeId);
        $toReject = $this->makeStudy(['efficiency_pct' => 20], $factoryId, $operationId, $employeeId);
        FactoryContext::set([$factoryId]);

        $this->assertCount(0, SkillMatrixRepository::getMatrix([])['cells']);

        Review::decide($toReject, 'rejected', 'Bad data.');
        $this->assertCount(0, SkillMatrixRepository::getMatrix([])['cells']);

        Review::decide($toApprove, 'approved', null);
        $cells = SkillMatrixRepository::getMatrix([])['cells'];
        $this->assertCount(1, $cells);
        $this->assertEquals(80.0, $cells[0]['avg_efficiency']);
        $this->assertEquals(1, $cells[0]['study_count']);
    }

    public function testApprovingAStudyOfATemplateThatDoesNotCountStillKeepsItOutOfTheMatrix()
    {
        $this->actingAsTestUser();
        $factoryId = $this->makeFactory();
        $operationId = $this->makeOperation();
        $employeeId = $this->makeEmployee($factoryId);
        $studyId = $this->makeStudy(['efficiency_pct' => 80, 'counts_in_matrix' => false], $factoryId, $operationId, $employeeId);
        FactoryContext::set([$factoryId]);

        Review::decide($studyId, 'approved', null);

        $this->assertCount(0, SkillMatrixRepository::getMatrix([])['cells']);
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function makeStudy(array $overrides = [], ?int $factoryId = null, ?int $operationId = null, ?int $employeeId = null): int
    {
        $factoryId = $factoryId ?? $this->makeFactory();

        return DB::table('time_studies')->insertGetId($overrides + [
            'factory_id' => $factoryId,
            'study_date' => '2026-10-20',
            'time_study_type' => 'interview_training',
            'operation_id' => $operationId ?? $this->makeOperation(),
            'employee_id' => $employeeId ?? $this->makeEmployee($factoryId),
            'status' => 'needs_review',
            'counts_in_matrix' => true,
            'efficiency_pct' => 75,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeFactory(): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 6));

        return DB::table('factories')->insertGetId(['name' => 'Plant '.$unique, 'code' => 'PL'.$unique, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function makeEmployee(int $factoryId): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $hierarchyId = DB::table('management_hierarchies')->insertGetId(['name' => 'Operator '.$unique, 'seq_no' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('employees')->insertGetId([
            'employee_no' => 'EMP-'.$unique, 'identification_no' => 'NIC-'.$unique, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'management_hierarchy_id' => $hierarchyId, 'factory_id' => $factoryId, 'employee_status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeOperation(): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $baseCategoryId = DB::table('base_operation_categories')->insertGetId(['name' => 'Cat '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $baseOperationId = DB::table('base_operations')->insertGetId(['name' => 'Op '.$unique, 'code' => 'BO'.$unique, 'base_operation_category_id' => $baseCategoryId, 'created_at' => now(), 'updated_at' => now()]);
        $gradeId = DB::table('operation_grades')->insertGetId(['name' => 'Grade '.$unique, 'code' => 'OG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $groupId = DB::table('product_groups')->insertGetId(['name' => 'Group '.$unique, 'code' => 'PG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Category '.$unique, 'code' => 'PC'.$unique, 'product_group_id' => $groupId, 'created_at' => now(), 'updated_at' => now()]);
        $machineCategoryId = DB::table('machine_categories')->insertGetId(['name' => 'MC '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $machineId = DB::table('machine_types')->insertGetId(['name' => 'Machine '.$unique, 'code' => 'MT'.$unique, 'machine_category_id' => $machineCategoryId, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('operations')->insertGetId([
            'code' => 'OP'.$unique, 'description' => 'Operation '.$unique, 'base_operation_id' => $baseOperationId, 'grade_id' => $gradeId,
            'product_category_id' => $categoryId, 'machine_type_id' => $machineId, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
