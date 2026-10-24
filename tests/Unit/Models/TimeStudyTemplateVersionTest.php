<?php

namespace Tests\Unit\Models;

use App\Exceptions\GeneralException;
use App\TimeStudyTemplate;
use App\TimeStudyTemplateVersion;
use Illuminate\Support\Facades\DB;
use Tests\Unit\Repositories\RepositoryTestCase;

class TimeStudyTemplateVersionTest extends RepositoryTestCase
{
    public function testAPublishedVersionCannotBeChanged()
    {
        $this->actingAsTestUser();
        $version = $this->makeVersion('published');

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('cannot be changed');

        $version->update(['definition' => ['rules' => ['first_last' => 'both']]]);
    }

    public function testAPublishedVersionCannotBeDeleted()
    {
        $this->actingAsTestUser();
        $version = $this->makeVersion('published');

        try {
            $version->delete();
            $this->fail('Expected the delete to be refused.');
        } catch (GeneralException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }

        $this->assertDatabaseHas('time_study_template_versions', ['id' => $version->id]);
    }

    public function testADraftVersionCanBeEditedAndThenPublishedOnce()
    {
        $this->actingAsTestUser();
        $version = $this->makeVersion('draft');

        $version->update(['definition' => ['rules' => ['first_last' => 'both']]]);
        $version->update(['status' => 'published', 'version_no' => 1, 'published_at' => now()]);

        $this->assertEquals('both', $version->fresh()->definition['rules']['first_last']);
        $this->expectException(GeneralException::class);
        $version->fresh()->update(['version_no' => 2]);
    }

    public function testTheLegacyTemplateExistsPublishedWithEveryRuleOff()
    {
        $version = TimeStudyTemplate::legacyVersion();
        $rules = $version->definition['rules'];

        $this->assertEquals('published', $version->status);
        $this->assertEquals('perf', $version->template->purpose);
        $this->assertEquals('never', $version->definition['approval']);
        $this->assertTrue($version->definition['counts_in_matrix']);
        foreach (['min_laps', 'outlier', 'variation', 'down_time'] as $rule) {
            $this->assertEquals('off', $rules[$rule]['severity'], $rule);
        }
    }

    public function testTemplateNamesAreUnique()
    {
        $this->actingAsTestUser();
        $this->makeVersion('draft', 'Same name');

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->makeVersion('draft', 'Same name');
    }

    private function makeVersion(string $status, string $name = null): TimeStudyTemplateVersion
    {
        $template = TimeStudyTemplate::create(['name' => $name ?? 'Template '.uniqid(), 'purpose' => 'perf']);

        return TimeStudyTemplateVersion::create([
            'time_study_template_id' => $template->id,
            'version_no' => $status === 'published' ? 1 : null,
            'status' => $status,
            'definition' => ['rules' => []],
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }
}
