<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class TimeStudyTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        $factoryId = DB::table('factories')->insertGetId([
            'name' => 'Plant '.uniqid(), 'code' => 'PL'.strtoupper(substr(uniqid(), -6)), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Template Tester', 'email' => 'tpl-'.uniqid().'@example.com', 'password' => bcrypt('Password1'),
            'is_active' => true, 'common_user_state' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('factory_user')->insert(['factory_id' => $factoryId, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser(User::find($userId)), 'X-Factory-Ids' => (string) $factoryId];
    }

    public function testTheTemplateEndpointsRequireAuthentication()
    {
        $this->getJson('/api/v1/timeStudyTemplates')->assertStatus(401);
        $this->postJson('/api/v1/timeStudyTemplates', [])->assertStatus(401);
    }

    public function testATemplateCanBeCreatedEditedPreviewedPublishedAndListed()
    {
        $h = $this->headers();

        $created = $this->postJson('/api/v1/timeStudyTemplates', ['name' => 'Collars', 'purpose' => 'perf', 'preset' => 'strict'], $h)
            ->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('data.draft.status', 'draft');
        $id = $created->json('data.id');

        $this->putJson("/api/v1/timeStudyTemplates/$id/draft", ['definition' => ['approval' => 'never']], $h)
            ->assertOk()->assertJsonPath('data.draft.definition.approval', 'never');

        $this->postJson('/api/v1/timeStudyTemplates/preview', ['template_id' => $id, 'name' => 'Collars', 'purpose' => 'perf'], $h)
            ->assertOk()->assertJsonPath('data.issues', []);

        $this->postJson("/api/v1/timeStudyTemplates/$id/publish", [], $h)
            ->assertOk()->assertJsonPath('data.published.version_no', 1)->assertJsonPath('data.draft', null);

        $this->getJson('/api/v1/timeStudyTemplates?q=Collars', $h)
            ->assertOk()->assertJsonPath('data.0.name', 'Collars')->assertJsonPath('data.0.published_version_no', 1);

        $this->getJson("/api/v1/timeStudyTemplates/$id", $h)->assertOk()->assertJsonPath('data.published.version_no', 1);
    }

    public function testValidationAndBusinessErrorsComeBackAs400WithAMessage()
    {
        $h = $this->headers();

        $this->postJson('/api/v1/timeStudyTemplates', ['name' => '', 'purpose' => 'perf'], $h)
            ->assertStatus(400)->assertJsonPath('status', 'error');

        $legacyId = DB::table('time_study_templates')->where('system_key', 'legacy')->value('id');
        $this->postJson("/api/v1/timeStudyTemplates/$legacyId/archive", [], $h)
            ->assertStatus(400)->assertJsonPath('message', fn ($m) => str_contains($m, 'used by the system'));
    }

    public function testAnUnknownTemplateIs400NotA500()
    {
        $this->getJson('/api/v1/timeStudyTemplates/999999', $this->headers())->assertStatus(400)->assertJsonPath('status', 'error');
    }

    public function testResolveNeedsAnOperationId()
    {
        $this->getJson('/api/v1/timeStudyTemplates/resolve', $this->headers())->assertStatus(400)->assertJsonPath('status', 'error');
    }

    public function testDiscardDuplicateAndArchiveRoutesWork()
    {
        $h = $this->headers();
        $id = $this->postJson('/api/v1/timeStudyTemplates', ['name' => 'T', 'purpose' => 'perf'], $h)->json('data.id');
        $this->postJson("/api/v1/timeStudyTemplates/$id/publish", [], $h)->assertOk();
        $this->putJson("/api/v1/timeStudyTemplates/$id/draft", ['name' => 'T2'], $h)->assertOk();

        $this->deleteJson("/api/v1/timeStudyTemplates/$id/draft", [], $h)->assertOk()->assertJsonPath('data.draft', null);
        $this->postJson("/api/v1/timeStudyTemplates/$id/duplicate", [], $h)->assertOk()->assertJsonPath('data.name', 'T2 (copy)');
        $this->postJson("/api/v1/timeStudyTemplates/$id/archive", [], $h)->assertOk();
        $this->getJson('/api/v1/timeStudyTemplates', $h)->assertOk()->assertJsonMissing(['name' => 'T2']);
    }
}
