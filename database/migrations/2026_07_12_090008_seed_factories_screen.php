<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Registers the "Factories" screen so it can be gated in the role/screen
// permission grid and appear in the sidebar via resources/navigator.php.
//
// PermissionRepository::updatePermissions() assumes a Permission row already
// exists for every (role, screen) pair — normally backfilled by
// generatePermissionsGrid() when a *role* is created, but nothing runs the
// equivalent when a *screen* is added directly like this. So this migration
// also inserts one ungranted Permission row per existing role for the new
// screen, mirroring what generatePermissionsGrid() would produce.
return new class extends Migration
{
    public function up()
    {
        $screenId = DB::table('screens')->insertGetId([
            'screen_name' => 'Factories',
            'screen_code' => 'ie/factories',
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
        $screenId = DB::table('screens')->where('screen_code', 'ie/factories')->value('id');
        DB::table('permissions')->where('screen_id', $screenId)->delete();
        DB::table('screens')->where('id', $screenId)->delete();
    }
};
