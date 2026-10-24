<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class TimeStudyReviewApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: array<string,string>, 1: int, 2: int} headers, factory id, user id */
    private function reviewer(): array
    {
        $factoryId = DB::table('factories')->insertGetId([
            'name' => 'Plant '.uniqid(), 'code' => 'PL'.strtoupper(substr(uniqid(), -6)), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Reviewer', 'email' => 'rev-'.uniqid().'@example.com', 'password' => bcrypt('Password1'),
            'is_active' => true, 'common_user_state' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('factory_user')->insert(['factory_id' => $factoryId, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);

        return [['Authorization' => 'Bearer '.JWTAuth::fromUser(User::find($userId)), 'X-Factory-Ids' => (string) $factoryId], $factoryId, $userId];
    }

    private function studyInFactory(int $factoryId, string $status = 'needs_review'): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $baseCategoryId = DB::table('base_operation_categories')->insertGetId(['name' => 'Cat '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $baseOperationId = DB::table('base_operations')->insertGetId(['name' => 'Op '.$unique, 'code' => 'BO'.$unique, 'base_operation_category_id' => $baseCategoryId, 'created_at' => now(), 'updated_at' => now()]);
        $gradeId = DB::table('operation_grades')->insertGetId(['name' => 'Grade '.$unique, 'code' => 'OG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $groupId = DB::table('product_groups')->insertGetId(['name' => 'Group '.$unique, 'code' => 'PG'.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Category '.$unique, 'code' => 'PC'.$unique, 'product_group_id' => $groupId, 'created_at' => now(), 'updated_at' => now()]);
        $machineCategoryId = DB::table('machine_categories')->insertGetId(['name' => 'MC '.$unique, 'created_at' => now(), 'updated_at' => now()]);
        $machineId = DB::table('machine_types')->insertGetId(['name' => 'Machine '.$unique, 'code' => 'MT'.$unique, 'machine_category_id' => $machineCategoryId, 'created_at' => now(), 'updated_at' => now()]);
        $operationId = DB::table('operations')->insertGetId([
            'code' => 'OP'.$unique, 'description' => 'Operation '.$unique, 'base_operation_id' => $baseOperationId, 'grade_id' => $gradeId,
            'product_category_id' => $categoryId, 'machine_type_id' => $machineId, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $hierarchyId = DB::table('management_hierarchies')->insertGetId(['name' => 'Operator '.$unique, 'seq_no' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $employeeId = DB::table('employees')->insertGetId([
            'employee_no' => 'EMP-'.$unique, 'identification_no' => 'NIC-'.$unique, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'management_hierarchy_id' => $hierarchyId, 'factory_id' => $factoryId, 'employee_status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('time_studies')->insertGetId([
            'factory_id' => $factoryId, 'study_date' => '2026-10-20', 'time_study_type' => 'interview_training', 'operation_id' => $operationId,
            'employee_id' => $employeeId, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function testReviewingRequiresAuthentication()
    {
        $this->postJson('/api/v1/timeStudies/1/review', ['decision' => 'approved'])->assertStatus(401);
    }

    public function testApprovingReturnsTheStudyWithItsReviewer()
    {
        [$headers, $factoryId, $userId] = $this->reviewer();
        $id = $this->studyInFactory($factoryId);

        $this->postJson("/api/v1/timeStudies/$id/review", ['decision' => 'approved', 'note' => 'Fine.'], $headers)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.review_note', 'Fine.')
            ->assertJsonPath('data.reviewed_by.id', $userId)
            ->assertJsonMissingPath('data.reviewed_by.password');
    }

    public function testRejectingWithoutAReasonIs400WithAMessage()
    {
        [$headers, $factoryId] = $this->reviewer();
        $id = $this->studyInFactory($factoryId);

        $this->postJson("/api/v1/timeStudies/$id/review", ['decision' => 'rejected'], $headers)
            ->assertStatus(400)->assertJsonPath('status', 'error')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Say why'));
    }

    public function testASecondDecisionIs400()
    {
        [$headers, $factoryId] = $this->reviewer();
        $id = $this->studyInFactory($factoryId);
        $this->postJson("/api/v1/timeStudies/$id/review", ['decision' => 'approved'], $headers)->assertOk();

        $this->postJson("/api/v1/timeStudies/$id/review", ['decision' => 'rejected', 'note' => 'x'], $headers)
            ->assertStatus(400)->assertJsonPath('message', fn ($m) => str_contains($m, 'already been approved'));
    }

    public function testAStudyFromAnotherFactoryCannotBeReviewed()
    {
        [$headers] = $this->reviewer();
        $otherFactory = DB::table('factories')->insertGetId(['name' => 'Other '.uniqid(), 'code' => 'OT'.strtoupper(substr(uniqid(), -6)), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $id = $this->studyInFactory($otherFactory);

        $this->postJson("/api/v1/timeStudies/$id/review", ['decision' => 'approved'], $headers)->assertStatus(400);
        $this->assertSame('needs_review', DB::table('time_studies')->where('id', $id)->value('status'));
    }

    public function testTheStudyDetailIncludesWhatTheReviewerNeeds()
    {
        [$headers, $factoryId] = $this->reviewer();
        $id = $this->studyInFactory($factoryId);

        $this->getJson("/api/v1/timeStudies/$id", $headers)
            ->assertOk()
            ->assertJsonStructure(['data' => ['status', 'quality_score', 'quality_breakdown', 'rule_flags', 'field_values', 'template_version', 'reviewed_by']]);
    }
}
