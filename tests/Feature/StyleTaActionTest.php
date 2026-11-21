<?php

namespace Tests\Feature;

use App\Http\Repositories\StyleTaActionRepository;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Support\ProductFamilies;
use Tests\TestCase;

/**
 * Covers the T&A Master (standard list) and a style's own T&A actions: the seeded list, CRUD,
 * ordering, applying the standard list to a style, and copying actions when a style is duplicated.
 */
class StyleTaActionTest extends TestCase
{
    use RefreshDatabase;

    /** Fixtures use the query builder: model boot() listeners need an authenticated user. */
    private function userId(): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Planner',
            'email' => 'planner-'.uniqid().'@example.com',
            'password' => bcrypt('Password1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser(User::find($this->userId()))];
    }

    /** Styles are factory-scoped, so the user must be linked to a factory that owns the style. */
    private function authHeadersForStyle(int $styleId): array
    {
        $unique = strtoupper(substr(md5(uniqid()), 0, 6));
        $factoryId = DB::table('factories')->insertGetId([
            'name' => 'TA Test Factory'.$unique, 'code' => 'TAF'.$unique, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('factory_style')->insert([
            'factory_id' => $factoryId, 'style_id' => $styleId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $userId = $this->userId();
        DB::table('factory_user')->insert([
            'factory_id' => $factoryId, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [
            'Authorization' => 'Bearer '.JWTAuth::fromUser(User::find($userId)),
            'X-Factory-Ids' => (string) $factoryId,
        ];
    }

    private function makeStyle(): int
    {
        $unique = strtoupper(substr(md5(uniqid()), 0, 6));
        $groupId = DB::table('product_groups')->insertGetId([
            'name' => 'Knits'.$unique, 'code' => 'KN'.$unique, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'T-Shirts'.$unique, 'code' => 'TS'.$unique,
            'product_family_id' => ProductFamilies::forGroup($groupId), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $customerId = DB::table('customers')->insertGetId([
            'description' => 'Decathlon'.$unique, 'code' => 'DC'.$unique, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $seasonId = DB::table('seasons')->insertGetId([
            'name' => 'Summer 27', 'code' => 'SU'.$unique, 'customer_id' => $customerId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('styles')->insertGetId([
            'name' => 'Style '.$unique, 'style_code' => 'ST'.$unique, 'product_category_id' => $categoryId,
            'customer_id' => $customerId, 'season_id' => $seasonId, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeDesignation(): int
    {
        $unique = strtoupper(substr(md5(uniqid()), 0, 6));

        return DB::table('designations')->insertGetId([
            'name' => 'Technical Manager '.$unique, 'code' => 'TM'.$unique, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeEmployee(): int
    {
        $unique = strtoupper(substr(md5(uniqid()), 0, 8));
        $factoryId = DB::table('factories')->insertGetId([
            'name' => 'Emp Plant '.$unique, 'code' => 'EP'.substr($unique, 0, 6), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $hierarchyId = DB::table('management_hierarchies')->insertGetId([
            'name' => 'Staff '.$unique, 'seq_no' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('employees')->insertGetId([
            'employee_no' => 'EMP-'.$unique, 'identification_no' => 'NIC-'.$unique, 'first_name' => 'Nimesh', 'last_name' => 'Perera',
            'management_hierarchy_id' => $hierarchyId, 'factory_id' => $factoryId, 'employee_status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeStyleAction(int $styleId, array $overrides = []): int
    {
        return DB::table('style_ta_actions')->insertGetId(array_merge([
            'style_id' => $styleId, 'phase' => 'Development', 'sequence' => 1, 'name' => 'Pattern Check',
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    // ── Seeded standard list ─────────────────────────────────────────────────

    public function testTheStandardListIsSeededFromTheSourceSheetWithCorrectedSpelling()
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v1/taTemplateActions');

        $response->assertStatus(200);
        $actions = collect($response->json('data'));
        $this->assertCount(36, $actions->where('phase', 'Development'));
        $this->assertCount(34, $actions->where('phase', 'Plant QCO'));
        $this->assertSame('Development', $actions->first()['phase']);

        $names = $actions->pluck('name')->implode('|');
        $this->assertStringContainsString('Embellishment Send', $names);
        $this->assertStringContainsString('Size Set Review (PE)', $names);
        $this->assertStringNotContainsString('Embilishment', $names);
        $this->assertStringNotContainsString('Emblishment', $names);
        $this->assertStringNotContainsString('Reviw', $names);
        $this->assertStringNotContainsString('Reveive', $names);
    }

    public function testSeededActionsAreUnmappedAndKeepProcessTimeVerbatim()
    {
        $actions = collect($this->withHeaders($this->authHeaders())->getJson('/api/v1/taTemplateActions')->json('data'));

        $this->assertTrue($actions->every(fn ($a) => $a['designation_id'] === null));
        $sizeSet = $actions->firstWhere('name', 'Send PE Package to Plant PE (Size Set)');
        $this->assertSame('H-0,(G,P,M)-1,OS-5', $sizeSet['process_time']);
        $this->assertNull($actions->firstWhere('name', 'Customer PP')['process_time']);
        $this->assertSame('Presign off +2day', $actions->firstWhere('name', 'Machine Requirement')['process_time']);
    }

    // ── T&A Master CRUD ──────────────────────────────────────────────────────

    public function testCreatesAMasterActionAtTheEndOfItsPhaseAndMapsADesignation()
    {
        $designationId = $this->makeDesignation();
        $lastDev = DB::table('ta_template_actions')->where('phase', 'Development')->max('sequence');

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/taTemplateActions', [
            'phase' => 'Development',
            'name' => '  Fit Approval  ',
            'designation_id' => $designationId,
            'process_time' => 'Presign off +5day',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success', 'data' => [
            'name' => 'Fit Approval',
            'sequence' => $lastDev + 1,
            'designation_id' => $designationId,
            'process_time' => 'Presign off +5day',
            'is_active' => true,
        ]]);
        $this->assertSame($designationId, $response->json('data.designation.id'));
    }

    public function testAllowsBlankProcessTimeAndDesignation()
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/taTemplateActions', [
            'phase' => 'Plant QCO', 'name' => 'Sign Off', 'process_time' => '', 'designation_id' => '',
        ]);

        $response->assertStatus(200);
        $this->assertNull($response->json('data.process_time'));
        $this->assertNull($response->json('data.designation_id'));
    }

    public function testRejectsAMasterActionWithoutNameOrPhase()
    {
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/taTemplateActions', ['phase' => 'Development'])->assertStatus(400);
        $this->withHeaders($headers)->postJson('/api/v1/taTemplateActions', ['name' => 'No phase'])->assertStatus(400);
    }

    public function testRejectsADuplicateNameInTheSamePhaseButAllowsItInAnotherPhase()
    {
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/taTemplateActions', ['phase' => 'Development', 'name' => 'Pattern Check'])
            ->assertStatus(400);
        $this->withHeaders($headers)->postJson('/api/v1/taTemplateActions', ['phase' => 'Plant QCO', 'name' => 'Pattern Check'])
            ->assertStatus(200);
    }

    public function testRejectsAnUnknownDesignation()
    {
        $this->withHeaders($this->authHeaders())->postJson('/api/v1/taTemplateActions', [
            'phase' => 'Development', 'name' => 'Brand New', 'designation_id' => 999999,
        ])->assertStatus(400);
    }

    public function testUpdatesAMasterActionAndRejectsAStaleUpdatedAt()
    {
        $headers = $this->authHeaders();
        $designationId = $this->makeDesignation();
        $row = DB::table('ta_template_actions')->where('name', 'Pattern Check')->first();

        $this->withHeaders($headers)->putJson('/api/v1/taTemplateActions/'.$row->id, [
            'designation_id' => $designationId, 'updated_at' => $row->updated_at,
        ])->assertStatus(200)->assertJson(['data' => ['designation_id' => $designationId, 'name' => 'Pattern Check']]);

        // The first save moved updated_at on, so reusing the old value is a lost-update attempt.
        DB::table('ta_template_actions')->where('id', $row->id)->update(['updated_at' => now()->addMinute()]);
        $this->withHeaders($headers)->putJson('/api/v1/taTemplateActions/'.$row->id, [
            'process_time' => '9', 'updated_at' => $row->updated_at,
        ])->assertStatus(400);
    }

    public function testMovingAnActionToAnotherPhasePutsItAtTheEndOfThatPhase()
    {
        $row = DB::table('ta_template_actions')->where('name', 'Pattern Check')->first();
        $lastQco = DB::table('ta_template_actions')->where('phase', 'Plant QCO')->max('sequence');

        $response = $this->withHeaders($this->authHeaders())->putJson('/api/v1/taTemplateActions/'.$row->id, [
            'phase' => 'Plant QCO', 'updated_at' => $row->updated_at,
        ]);

        $response->assertStatus(200)->assertJson(['data' => ['phase' => 'Plant QCO', 'sequence' => $lastQco + 1]]);
    }

    public function testReordersAPhaseAndKeepsUnlistedActionsAfterTheListedOnes()
    {
        $ids = DB::table('ta_template_actions')->where('phase', 'Plant QCO')->orderBy('sequence')->pluck('id')->all();
        $count = count($ids);
        // Put the last action first; list only two ids — the rest must follow in their old order.
        $request = [$ids[$count - 1], $ids[0]];

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/taTemplateActions/reorder', [
            'phase' => 'Plant QCO', 'ids' => $request,
        ]);

        $response->assertStatus(200);
        $resulting = collect($response->json('data'));
        $this->assertSame($request[0], $resulting[0]['id']);
        $this->assertSame($request[1], $resulting[1]['id']);
        $this->assertSame(range(1, $count), $resulting->pluck('sequence')->all());
        $this->assertSame($ids[1], $resulting[2]['id']);
    }

    public function testReorderRejectsAnActionFromAnotherPhase()
    {
        $developmentId = DB::table('ta_template_actions')->where('phase', 'Development')->value('id');

        $this->withHeaders($this->authHeaders())->postJson('/api/v1/taTemplateActions/reorder', [
            'phase' => 'Plant QCO', 'ids' => [$developmentId],
        ])->assertStatus(400);
    }

    public function testDeletesAMasterActionWithoutDeletingTheStylesCopy()
    {
        $styleId = $this->makeStyle();
        $templateId = DB::table('ta_template_actions')->where('name', 'Pattern Check')->value('id');
        $actionId = $this->makeStyleAction($styleId, ['ta_template_action_id' => $templateId]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/masterDetails', ['TaTemplateAction' => ['DEL' => [$templateId]]])
            ->assertStatus(200);

        $this->assertDatabaseMissing('ta_template_actions', ['id' => $templateId]);
        $this->assertDatabaseHas('style_ta_actions', ['id' => $actionId, 'ta_template_action_id' => null]);
    }

    // ── Apply the standard list to a style ───────────────────────────────────

    public function testApplyingTheStandardListCopiesEveryActiveActionWithoutDates()
    {
        $styleId = $this->makeStyle();
        DB::table('ta_template_actions')->where('name', 'Pattern Check')->update(['is_active' => false]);

        $response = $this->withHeaders($this->authHeadersForStyle($styleId))
            ->postJson('/api/v1/styleTaActions/applyTemplate', ['style_id' => $styleId]);

        $response->assertStatus(200)->assertJson(['data' => ['created' => 69, 'skipped' => 0]]);
        $this->assertDatabaseCount('style_ta_actions', 69);
        $this->assertDatabaseMissing('style_ta_actions', ['style_id' => $styleId, 'name' => 'Pattern Check']);
        $this->assertSame(0, DB::table('style_ta_actions')->whereNotNull('planned_date')->orWhereNotNull('actual_date')->count());
    }

    public function testApplyingTwiceIsIdempotentAndOnlyAddsWhatIsMissing()
    {
        $styleId = $this->makeStyle();
        $headers = $this->authHeadersForStyle($styleId);
        $this->withHeaders($headers)->postJson('/api/v1/styleTaActions/applyTemplate', ['style_id' => $styleId])->assertStatus(200);

        $again = $this->withHeaders($headers)->postJson('/api/v1/styleTaActions/applyTemplate', ['style_id' => $styleId]);
        $again->assertJson(['data' => ['created' => 0, 'skipped' => 70]]);

        $this->withHeaders($this->authHeaders())->postJson('/api/v1/taTemplateActions', ['phase' => 'Development', 'name' => 'Fit Approval']);
        $third = $this->withHeaders($headers)->postJson('/api/v1/styleTaActions/applyTemplate', ['style_id' => $styleId]);
        $third->assertJson(['data' => ['created' => 1, 'skipped' => 70]]);
    }

    public function testApplyingChosenTemplatesOnlyCopiesThose()
    {
        $styleId = $this->makeStyle();
        $ids = DB::table('ta_template_actions')->orderBy('id')->limit(3)->pluck('id')->all();

        $this->withHeaders($this->authHeadersForStyle($styleId))
            ->postJson('/api/v1/styleTaActions/applyTemplate', ['style_id' => $styleId, 'template_ids' => $ids])
            ->assertJson(['data' => ['created' => 3]]);
    }

    public function testApplyingToAnUnknownStyleFails()
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/styleTaActions/applyTemplate', ['style_id' => 999999])
            ->assertStatus(400);
    }

    public function testApplyingCarriesTheMappedDesignationAndProcessTimeOntoTheStyle()
    {
        $styleId = $this->makeStyle();
        $designationId = $this->makeDesignation();
        DB::table('ta_template_actions')->where('name', 'Pattern Check')->update(['designation_id' => $designationId]);

        $this->withHeaders($this->authHeadersForStyle($styleId))
            ->postJson('/api/v1/styleTaActions/applyTemplate', ['style_id' => $styleId])->assertStatus(200);

        $this->assertDatabaseHas('style_ta_actions', [
            'style_id' => $styleId, 'name' => 'Pattern Check', 'designation_id' => $designationId, 'process_time' => '-1',
        ]);
    }

    // ── A style's own actions ────────────────────────────────────────────────

    public function testCreatesACustomStyleActionWithAnEmployeeIndependentOfTheDesignation()
    {
        $styleId = $this->makeStyle();
        $designationId = $this->makeDesignation();
        $employeeId = $this->makeEmployee(); // not a holder of $designationId on purpose

        $response = $this->withHeaders($this->authHeadersForStyle($styleId))->postJson('/api/v1/styleTaActions', [
            'style_id' => $styleId, 'phase' => 'Development', 'name' => 'Customer Visit Prep',
            'designation_id' => $designationId, 'employee_id' => $employeeId,
            'process_time' => 'Presign off +4day', 'planned_date' => '2026-12-01', 'remarks' => 'Bring samples',
        ]);

        $response->assertStatus(200)->assertJson(['data' => [
            'name' => 'Customer Visit Prep', 'sequence' => 1, 'planned_date' => '2026-12-01', 'actual_date' => null,
            'designation_id' => $designationId, 'employee_id' => $employeeId,
        ]]);
        $this->assertSame('Nimesh', $response->json('data.employee.first_name'));
    }

    public function testRejectsAStyleActionWithoutAName()
    {
        $styleId = $this->makeStyle();

        $this->withHeaders($this->authHeadersForStyle($styleId))
            ->postJson('/api/v1/styleTaActions', ['style_id' => $styleId, 'phase' => 'Development'])
            ->assertStatus(400);
    }

    public function testRejectsADuplicateActionNameWithinTheSameStyleAndPhase()
    {
        $styleId = $this->makeStyle();
        $this->makeStyleAction($styleId, ['name' => 'Pattern Check']);

        $this->withHeaders($this->authHeadersForStyle($styleId))->postJson('/api/v1/styleTaActions', [
            'style_id' => $styleId, 'phase' => 'Development', 'name' => 'Pattern Check',
        ])->assertStatus(400);
    }

    public function testRejectsAnInvalidDateAndAnUnknownEmployee()
    {
        $styleId = $this->makeStyle();
        $headers = $this->authHeadersForStyle($styleId);

        $this->withHeaders($headers)->postJson('/api/v1/styleTaActions', [
            'style_id' => $styleId, 'phase' => 'Development', 'name' => 'A', 'planned_date' => 'not-a-date',
        ])->assertStatus(400);
        $this->withHeaders($headers)->postJson('/api/v1/styleTaActions', [
            'style_id' => $styleId, 'phase' => 'Development', 'name' => 'B', 'employee_id' => 999999,
        ])->assertStatus(400);
    }

    public function testMarksAnActionDoneByUpdatingItsActualDate()
    {
        $styleId = $this->makeStyle();
        $actionId = $this->makeStyleAction($styleId, ['planned_date' => '2026-12-01']);
        $row = DB::table('style_ta_actions')->find($actionId);

        $response = $this->withHeaders($this->authHeadersForStyle($styleId))->putJson('/api/v1/styleTaActions/'.$actionId, [
            'actual_date' => '2026-12-03', 'updated_at' => $row->updated_at,
        ]);

        // Partial payload: untouched fields (planned date, name) must survive.
        $response->assertStatus(200)->assertJson(['data' => [
            'actual_date' => '2026-12-03', 'planned_date' => '2026-12-01', 'name' => 'Pattern Check',
        ]]);
    }

    public function testClearingAnOwnerAndDateWithNullKeepsTheAction()
    {
        $styleId = $this->makeStyle();
        $employeeId = $this->makeEmployee();
        $actionId = $this->makeStyleAction($styleId, ['employee_id' => $employeeId, 'actual_date' => '2026-12-03']);
        $row = DB::table('style_ta_actions')->find($actionId);

        $response = $this->withHeaders($this->authHeadersForStyle($styleId))->putJson('/api/v1/styleTaActions/'.$actionId, [
            'employee_id' => null, 'actual_date' => null, 'updated_at' => $row->updated_at,
        ]);

        $response->assertStatus(200);
        $this->assertNull($response->json('data.employee_id'));
        $this->assertNull($response->json('data.actual_date'));
    }

    public function testListsAStylesActionsInPhaseThenSequenceOrder()
    {
        $styleId = $this->makeStyle();
        $this->makeStyleAction($styleId, ['phase' => 'Plant QCO', 'sequence' => 1, 'name' => 'Q1']);
        $this->makeStyleAction($styleId, ['phase' => 'Development', 'sequence' => 2, 'name' => 'D2']);
        $this->makeStyleAction($styleId, ['phase' => 'Development', 'sequence' => 1, 'name' => 'D1']);
        $this->makeStyleAction($this->makeStyle(), ['name' => 'Other style']);

        $response = $this->withHeaders($this->authHeadersForStyle($styleId))->getJson('/api/v1/styleTaActions/byStyle/'.$styleId);

        $response->assertStatus(200);
        $this->assertSame(['D1', 'D2', 'Q1'], collect($response->json('data'))->pluck('name')->all());
    }

    public function testAnUnauthenticatedRequestIsRejected()
    {
        $this->getJson('/api/v1/taTemplateActions')->assertStatus(401);
        $this->postJson('/api/v1/styleTaActions/applyTemplate', ['style_id' => 1])->assertStatus(401);
    }

    // ── Tracker ──────────────────────────────────────────────────────────────

    /** One style with one action per status, relative to a fixed "today" of 2026-11-15. */
    private function styleWithOneActionPerStatus(): int
    {
        $styleId = $this->makeStyle();
        $this->makeStyleAction($styleId, ['name' => 'Done', 'sequence' => 1, 'planned_date' => '2026-11-01', 'actual_date' => '2026-11-02']);
        $this->makeStyleAction($styleId, ['name' => 'Overdue', 'sequence' => 2, 'planned_date' => '2026-11-10']);
        $this->makeStyleAction($styleId, ['name' => 'Due soon', 'sequence' => 3, 'planned_date' => '2026-11-20']);
        $this->makeStyleAction($styleId, ['name' => 'Upcoming', 'sequence' => 4, 'planned_date' => '2026-12-30']);
        $this->makeStyleAction($styleId, ['name' => 'Not scheduled', 'sequence' => 5]);

        return $styleId;
    }

    public function testTrackerSummaryCountsEachStatusAndIgnoresTheStatusFilter()
    {
        $styleId = $this->styleWithOneActionPerStatus();

        $response = $this->withHeaders($this->authHeadersForStyle($styleId))->postJson('/api/v1/styleTaActions/filter', [
            'style_id' => [$styleId], 'status' => 'overdue', 'today' => '2026-11-15',
        ]);

        $response->assertStatus(200)->assertJson(['meta' => [
            'total' => 1,
            'summary' => ['total' => 5, 'done' => 1, 'overdue' => 1, 'due_soon' => 1, 'upcoming' => 1, 'not_scheduled' => 1],
        ]]);
        $this->assertSame(['Overdue'], collect($response->json('data'))->pluck('name')->all());
    }

    public function testTrackerListsMostUrgentFirstAndDoneLast()
    {
        $styleId = $this->styleWithOneActionPerStatus();

        $response = $this->withHeaders($this->authHeadersForStyle($styleId))->postJson('/api/v1/styleTaActions/filter', [
            'style_id' => [$styleId], 'today' => '2026-11-15',
        ]);

        $this->assertSame(
            ['Overdue', 'Due soon', 'Upcoming', 'Not scheduled', 'Done'],
            collect($response->json('data'))->pluck('name')->all()
        );
    }

    public function testTrackerFiltersByPhaseOwnerAndDueRange()
    {
        $styleId = $this->makeStyle();
        $employeeId = $this->makeEmployee();
        $this->makeStyleAction($styleId, ['name' => 'Mine', 'phase' => 'Plant QCO', 'employee_id' => $employeeId, 'planned_date' => '2026-11-18']);
        $this->makeStyleAction($styleId, ['name' => 'Other phase', 'phase' => 'Development', 'employee_id' => $employeeId, 'planned_date' => '2026-11-18']);
        $this->makeStyleAction($styleId, ['name' => 'Other owner', 'phase' => 'Plant QCO', 'planned_date' => '2026-11-18']);
        $this->makeStyleAction($styleId, ['name' => 'Out of range', 'phase' => 'Plant QCO', 'employee_id' => $employeeId, 'planned_date' => '2026-12-30']);

        $response = $this->withHeaders($this->authHeadersForStyle($styleId))->postJson('/api/v1/styleTaActions/filter', [
            'phase' => ['Plant QCO'], 'employee_id' => [$employeeId], 'due_from' => '2026-11-15', 'due_to' => '2026-11-30', 'today' => '2026-11-15',
        ]);

        $this->assertSame(['Mine'], collect($response->json('data'))->pluck('name')->all());
    }

    public function testTrackerSearchMatchesActionNameAndTreatsWildcardsLiterally()
    {
        $styleId = $this->makeStyle();
        $this->makeStyleAction($styleId, ['name' => 'Pattern Check', 'sequence' => 1]);
        $this->makeStyleAction($styleId, ['name' => '100% Cotton Check', 'sequence' => 2]);
        $headers = $this->authHeadersForStyle($styleId);

        $byName = $this->withHeaders($headers)->postJson('/api/v1/styleTaActions/filter', ['search' => 'pattern']);
        $this->assertSame(['Pattern Check'], collect($byName->json('data'))->pluck('name')->all());

        // "%" must match a literal percent sign, not "anything".
        $literal = $this->withHeaders($headers)->postJson('/api/v1/styleTaActions/filter', ['search' => '100%']);
        $this->assertSame(['100% Cotton Check'], collect($literal->json('data'))->pluck('name')->all());
    }

    public function testTrackerPaginatesAndClampsThePageSize()
    {
        $styleId = $this->makeStyle();
        foreach (range(1, 5) as $i) {
            $this->makeStyleAction($styleId, ['name' => 'Action '.$i, 'sequence' => $i]);
        }

        $response = $this->withHeaders($this->authHeadersForStyle($styleId))->postJson('/api/v1/styleTaActions/filter', [
            'style_id' => [$styleId], 'page' => 2, 'per_page' => 2,
        ]);

        $response->assertStatus(200)->assertJson(['meta' => ['total' => 5, 'per_page' => 2, 'current_page' => 2, 'last_page' => 3]]);
        $this->assertCount(2, $response->json('data'));

        $huge = $this->withHeaders($this->authHeadersForStyle($styleId))->postJson('/api/v1/styleTaActions/filter', ['per_page' => 100000]);
        $this->assertSame(200, $huge->json('meta.per_page'));
    }

    public function testTrackerRejectsAnUnknownStatus()
    {
        $styleId = $this->makeStyle();

        $this->withHeaders($this->authHeadersForStyle($styleId))
            ->postJson('/api/v1/styleTaActions/filter', ['status' => 'bogus'])
            ->assertStatus(400);
    }

    public function testTrackerAndByStyleNeverShowActionsOfStylesOutsideTheUsersFactories()
    {
        $mine = $this->makeStyle();
        $theirs = $this->makeStyle();
        $this->makeStyleAction($mine, ['name' => 'Visible']);
        $this->makeStyleAction($theirs, ['name' => 'Hidden']);
        $headers = $this->authHeadersForStyle($mine);

        $tracker = $this->withHeaders($headers)->postJson('/api/v1/styleTaActions/filter', []);
        $this->assertSame(['Visible'], collect($tracker->json('data'))->pluck('name')->all());

        $byStyle = $this->withHeaders($headers)->getJson('/api/v1/styleTaActions/byStyle/'.$theirs);
        $this->assertSame([], $byStyle->json('data'));
    }

    // ── Suggested dates (bulk schedule) ──────────────────────────────────────

    public function testApplyingAScheduleSetsOnlyThePlannedDatesOfTheListedActions()
    {
        $styleId = $this->makeStyle();
        $a = $this->makeStyleAction($styleId, ['name' => 'A', 'sequence' => 1]);
        $b = $this->makeStyleAction($styleId, ['name' => 'B', 'sequence' => 2, 'planned_date' => '2026-01-01']);
        $c = $this->makeStyleAction($styleId, ['name' => 'C', 'sequence' => 3]);

        $response = $this->withHeaders($this->authHeadersForStyle($styleId))->postJson('/api/v1/styleTaActions/applySchedule', [
            'style_id' => $styleId,
            'items' => [['id' => $a, 'planned_date' => '2026-12-01'], ['id' => $b, 'planned_date' => '2026-12-02']],
        ]);

        $response->assertStatus(200)->assertJson(['data' => ['updated' => 2]]);
        $this->assertDatabaseHas('style_ta_actions', ['id' => $a, 'planned_date' => '2026-12-01']);
        $this->assertDatabaseHas('style_ta_actions', ['id' => $b, 'planned_date' => '2026-12-02']);
        $this->assertDatabaseHas('style_ta_actions', ['id' => $c, 'planned_date' => null]);
    }

    public function testApplyingAScheduleIsAllOrNothingWhenAnActionBelongsToAnotherStyle()
    {
        $styleId = $this->makeStyle();
        $mine = $this->makeStyleAction($styleId, ['name' => 'Mine']);
        $foreign = $this->makeStyleAction($this->makeStyle(), ['name' => 'Foreign']);

        $this->withHeaders($this->authHeadersForStyle($styleId))->postJson('/api/v1/styleTaActions/applySchedule', [
            'style_id' => $styleId,
            'items' => [['id' => $mine, 'planned_date' => '2026-12-01'], ['id' => $foreign, 'planned_date' => '2026-12-01']],
        ])->assertStatus(400);

        $this->assertDatabaseHas('style_ta_actions', ['id' => $mine, 'planned_date' => null]);
    }

    public function testApplyingAScheduleRejectsABadDateOrAnEmptyList()
    {
        $styleId = $this->makeStyle();
        $id = $this->makeStyleAction($styleId);
        $headers = $this->authHeadersForStyle($styleId);

        $this->withHeaders($headers)->postJson('/api/v1/styleTaActions/applySchedule', [
            'style_id' => $styleId, 'items' => [['id' => $id, 'planned_date' => 'tomorrow-ish']],
        ])->assertStatus(400);
        $this->withHeaders($headers)->postJson('/api/v1/styleTaActions/applySchedule', ['style_id' => $styleId, 'items' => []])
            ->assertStatus(400);
    }

    // ── Excel import / export ────────────────────────────────────────────────

    public function testMasterImportMapsDesignationsByNameOrCodeAndLeavesBlankCellsUntouched()
    {
        $byName = $this->makeDesignation();
        $byCodeId = $this->makeDesignation();
        $code = DB::table('designations')->where('id', $byCodeId)->value('code');
        $name = DB::table('designations')->where('id', $byName)->value('name');
        $before = DB::table('ta_template_actions')->where('name', 'Pattern Check')->first();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/taTemplateActions/import', ['rows' => [
            ['phase' => 'development', 'name' => 'pattern check', 'designation' => $name, 'process_time' => ''],
            ['phase' => 'Development', 'name' => 'GRN/Inspection', 'designation' => strtolower($code)],
            ['phase' => 'Plant QCO', 'name' => 'Brand New Action', 'process_time' => 'Weekly', 'is_active' => 'No'],
        ]]);

        $response->assertStatus(200)->assertJson(['data' => ['total' => 3, 'created' => 1, 'updated' => 2, 'failed' => []]]);
        $this->assertDatabaseHas('ta_template_actions', ['name' => 'Pattern Check', 'designation_id' => $byName, 'process_time' => $before->process_time]);
        $this->assertDatabaseHas('ta_template_actions', ['name' => 'GRN/Inspection', 'designation_id' => $byCodeId]);
        $this->assertDatabaseHas('ta_template_actions', ['name' => 'Brand New Action', 'process_time' => 'Weekly', 'is_active' => false]);
    }

    public function testMasterImportReportsBadRowsWithoutStoppingTheRest()
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/taTemplateActions/import', ['rows' => [
            ['phase' => 'Development', 'name' => 'Pattern Check', 'designation' => 'No Such Designation'],
            ['phase' => 'Development', 'name' => '', 'process_time' => '1'],
            ['phase' => 'Development', 'name' => 'GRN/Inspection', 'is_active' => 'maybe'],
            ['phase' => 'Development', 'name' => 'Receive Samples', 'process_time' => '2'],
            ['phase' => 'Development', 'name' => 'receive samples', 'process_time' => '3'],
        ]]);

        $response->assertStatus(200)->assertJson(['data' => ['total' => 5, 'created' => 1, 'updated' => 0]]);
        $failed = collect($response->json('data.failed'))->keyBy('row');
        $this->assertSame([2, 3, 4, 6], $failed->keys()->sort()->values()->all());
        $this->assertStringContainsString('not found', $failed[2]['error']);
        $this->assertStringContainsString('Phase and Action are required', $failed[3]['error']);
        $this->assertStringContainsString('Duplicate', $failed[6]['error']);
    }

    public function testMasterImportRefusesAnAmbiguousDesignationName()
    {
        DB::table('designations')->insert([
            ['name' => 'Shared Name', 'code' => 'SH1', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Shared Name', 'code' => 'SH2', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/taTemplateActions/import', ['rows' => [
            ['phase' => 'Development', 'name' => 'Pattern Check', 'designation' => 'Shared Name'],
        ]]);

        $this->assertStringContainsString('more than one', $response->json('data.failed.0.error'));
    }

    public function testStyleImportUpdatesDatesAndOwnerCreatesMissingActionsAndKeepsBlanks()
    {
        $styleId = $this->makeStyle();
        $styleName = DB::table('styles')->where('id', $styleId)->value('name');
        $employeeId = $this->makeEmployee();
        $employeeNo = DB::table('employees')->where('id', $employeeId)->value('employee_no');
        $existing = $this->makeStyleAction($styleId, ['name' => 'Pattern Check', 'process_time' => '-1', 'remarks' => 'keep me']);

        $response = $this->withHeaders($this->authHeadersForStyle($styleId))->postJson('/api/v1/styleTaActions/import', ['rows' => [
            ['style' => strtoupper($styleName), 'phase' => 'Development', 'name' => 'pattern check', 'employee_no' => $employeeNo, 'planned_date' => '2026-12-01', 'remarks' => ''],
            ['style' => $styleName, 'phase' => 'Plant QCO', 'name' => 'Site Visit', 'process_time' => '4', 'planned_date' => '2026-12-05'],
        ]]);

        $response->assertStatus(200)->assertJson(['data' => ['total' => 2, 'created' => 1, 'updated' => 1, 'failed' => []]]);
        $this->assertDatabaseHas('style_ta_actions', [
            'id' => $existing, 'employee_id' => $employeeId, 'planned_date' => '2026-12-01', 'process_time' => '-1', 'remarks' => 'keep me',
        ]);
        $this->assertDatabaseHas('style_ta_actions', ['style_id' => $styleId, 'phase' => 'Plant QCO', 'name' => 'Site Visit', 'planned_date' => '2026-12-05']);
    }

    public function testStyleImportReportsUnknownStyleEmployeeAndBadDates()
    {
        $styleId = $this->makeStyle();
        $styleName = DB::table('styles')->where('id', $styleId)->value('name');

        $response = $this->withHeaders($this->authHeadersForStyle($styleId))->postJson('/api/v1/styleTaActions/import', ['rows' => [
            ['style' => 'Ghost Style', 'phase' => 'Development', 'name' => 'A'],
            ['style' => $styleName, 'phase' => 'Development', 'name' => 'B', 'employee_no' => 'NOPE-1'],
            ['style' => $styleName, 'phase' => 'Development', 'name' => 'C', 'planned_date' => 'not a date'],
            ['style' => $styleName, 'phase' => '', 'name' => 'D'],
        ]]);

        $response->assertStatus(200)->assertJson(['data' => ['total' => 4, 'created' => 0, 'updated' => 0]]);
        $this->assertCount(4, $response->json('data.failed'));
        $this->assertDatabaseCount('style_ta_actions', 0);
    }

    public function testExportsDownloadAsSpreadsheets()
    {
        $styleId = $this->styleWithOneActionPerStatus();

        $master = $this->withHeaders($this->authHeaders())->post('/api/v1/taTemplateActions/export');
        $style = $this->withHeaders($this->authHeadersForStyle($styleId))->post('/api/v1/styleTaActions/export', ['style_id' => [$styleId]]);

        $master->assertStatus(200)->assertHeader('content-disposition');
        $style->assertStatus(200)->assertHeader('content-disposition');
        $this->assertStringContainsString('.xlsx', $style->headers->get('content-disposition'));
    }

    // ── Copying when a style is duplicated ───────────────────────────────────

    public function testCopyingToAnotherStyleKeepsThePlanButDropsTheDates()
    {
        $source = $this->makeStyle();
        $target = $this->makeStyle();
        $designationId = $this->makeDesignation();
        $employeeId = $this->makeEmployee();
        $a = $this->makeStyleAction($source, [
            'name' => 'Pattern Check', 'designation_id' => $designationId, 'employee_id' => $employeeId, 'process_time' => '-1',
            'remarks' => 'Check grain', 'planned_date' => '2026-12-01', 'actual_date' => '2026-12-02',
        ]);
        $this->makeStyleAction($source, ['name' => 'Not chosen', 'sequence' => 2]);

        $this->actingAsUser();
        $copied = StyleTaActionRepository::copyToStyle($source, $target, [$a]);

        $this->assertSame(1, $copied);
        $this->assertDatabaseHas('style_ta_actions', [
            'style_id' => $target, 'name' => 'Pattern Check', 'designation_id' => $designationId, 'employee_id' => $employeeId,
            'process_time' => '-1', 'remarks' => 'Check grain', 'planned_date' => null, 'actual_date' => null,
        ]);
        $this->assertDatabaseMissing('style_ta_actions', ['style_id' => $target, 'name' => 'Not chosen']);
    }

    public function testCopyingRejectsAnActionThatBelongsToAnotherStyle()
    {
        $source = $this->makeStyle();
        $foreignActionId = $this->makeStyleAction($this->makeStyle(), ['name' => 'Elsewhere']);

        $this->actingAsUser();
        $this->expectException(\App\Exceptions\GeneralException::class);
        StyleTaActionRepository::copyToStyle($source, $this->makeStyle(), [$foreignActionId]);
    }

    public function testCopyingNothingCopiesNothing()
    {
        $this->assertSame(0, StyleTaActionRepository::copyToStyle($this->makeStyle(), $this->makeStyle(), []));
    }

    /** Model listeners stamp created_by from Auth::user(), so repository-level calls need a logged-in user. */
    private function actingAsUser(): void
    {
        $this->actingAs(User::find($this->userId()), 'api');
    }
}
