<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Registers the "Time Study" and "Time Study Downtime Reasons" screens so they
// can be gated in the role/screen permission grid and appear in the sidebar
// via resources/navigator.php. Same backfill rationale as
// 2026_07_26_000009_seed_customer_and_related_screen.php.
return new class extends Migration
{
    private array $screens = [
        ['screen_name' => 'Time Study', 'screen_code' => 'ie/time-study'],
        ['screen_name' => 'Time Study Downtime Reasons', 'screen_code' => 'ie/time-study-downtime-reasons'],
    ];

    public function up()
    {
        $roleIds = DB::table('roles')->pluck('id');
        $now = now();

        foreach ($this->screens as $screen) {
            $screenId = DB::table('screens')->insertGetId([
                'screen_name' => $screen['screen_name'],
                'screen_code' => $screen['screen_code'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

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
    }

    public function down()
    {
        foreach ($this->screens as $screen) {
            $screenId = DB::table('screens')->where('screen_code', $screen['screen_code'])->value('id');
            DB::table('permissions')->where('screen_id', $screenId)->delete();
            DB::table('screens')->where('id', $screenId)->delete();
        }
    }
};
