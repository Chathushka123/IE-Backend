<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Registers the "Time Study Reports" screen so it can be gated in the
// role/screen permission grid and appear in the sidebar via
// resources/navigator.php. Same backfill rationale as
// 2026_08_04_000006_seed_time_study_screens.php.
return new class extends Migration
{
    private string $screenName = 'Time Study Reports';
    private string $screenCode = 'ie/time-study-report';

    public function up()
    {
        $screenId = DB::table('screens')->insertGetId([
            'screen_name' => $this->screenName,
            'screen_code' => $this->screenCode,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleIds = DB::table('roles')->pluck('id');
        $now = now();
        DB::table('permissions')->insert(
            $roleIds->map(fn ($roleId) => [
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
