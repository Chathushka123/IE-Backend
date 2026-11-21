<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Registers the "T&A Master" screen so it can be gated in the role/screen permission grid and appear
// in the sidebar via resources/navigator.php. Same backfill rationale as
// 2026_10_25_000007_seed_time_study_templates_screen.php (screen_name must match the navigator caption).
return new class extends Migration
{
    private string $screenName = 'T&A Master';
    private string $screenCode = 'ie/ta-master';

    public function up()
    {
        $now = now();
        $screenId = DB::table('screens')->insertGetId([
            'screen_name' => $this->screenName,
            'screen_code' => $this->screenCode,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('permissions')->insert(
            DB::table('roles')->pluck('id')->map(fn ($roleId) => [
                'role_id' => $roleId,
                'screen_id' => $screenId,
                'factory_id' => null,
                'grant' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down()
    {
        $screenId = DB::table('screens')->where('screen_code', $this->screenCode)->value('id');
        DB::table('permissions')->where('screen_id', $screenId)->delete();
        DB::table('screens')->where('id', $screenId)->delete();
    }
};
