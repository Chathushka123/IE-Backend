<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Creates the global "Legacy" template that every study recorded before templates existed (and
// every study still saved by a client that does not send a template) runs under. All rules are
// off, studies are approved automatically and count towards the skill matrix, so behaviour is
// unchanged. Limits are the standard ones so the quality score stays comparable.
// Existing rows get the Legacy version; their purpose/status/counts_in_matrix already carry the
// column defaults. No aggregate is recomputed: historical numbers are left exactly as stored.
return new class extends Migration
{
    private const SYSTEM_KEY = 'legacy';

    public function up(): void
    {
        $now = now();

        $templateId = DB::table('time_study_templates')->insertGetId([
            'name' => 'Legacy (before templates)',
            'system_key' => self::SYSTEM_KEY,
            'purpose' => 'perf',
            'manual_only' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $versionId = DB::table('time_study_template_versions')->insertGetId([
            'time_study_template_id' => $templateId,
            'version_no' => 1,
            'status' => 'published',
            'definition' => json_encode([
                'rules' => [
                    'min_laps' => ['value' => 8, 'severity' => 'off'],
                    'outlier' => ['pct' => 30, 'severity' => 'off'],
                    'variation' => ['pct' => 20, 'severity' => 'off'],
                    'down_time' => ['pct' => 15, 'severity' => 'off'],
                    'first_last' => 'ask',
                ],
                'fields' => [],
                'counts_in_matrix' => true,
                'approval' => 'never',
            ]),
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('time_studies')->whereNull('template_version_id')->update(['template_version_id' => $versionId]);
    }

    public function down(): void
    {
        $templateId = DB::table('time_study_templates')->where('system_key', self::SYSTEM_KEY)->value('id');
        if ($templateId === null) {
            return;
        }

        $versionIds = DB::table('time_study_template_versions')->where('time_study_template_id', $templateId)->pluck('id');
        DB::table('time_studies')->whereIn('template_version_id', $versionIds)->update(['template_version_id' => null]);
        DB::table('time_study_template_versions')->whereIn('id', $versionIds)->delete();
        DB::table('time_study_templates')->where('id', $templateId)->delete();
    }
};
