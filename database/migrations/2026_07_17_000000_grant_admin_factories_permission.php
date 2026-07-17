<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026_07_12_090008_seed_factories_screen.php inserted an ungranted (grant =
// NULL) Permission row for every role on the new "Factories" screen,
// including Admin. That left non-sysadmin Admin-role users unable to access
// the Factories screen, since only the sysadmin@gmail.com account bypasses
// the permissions table entirely (PermissionRepository::isAuthorized /
// getNavigator). Admin should have full ("w") access to every screen, same
// as it does everywhere else.
return new class extends Migration
{
    public function up()
    {
        $adminRoleId = DB::table('roles')->where('role_code', 'Admin')->value('id');
        $factoriesScreenId = DB::table('screens')->where('screen_code', 'ie/factories')->value('id');

        DB::table('permissions')
            ->where('role_id', $adminRoleId)
            ->where('screen_id', $factoriesScreenId)
            ->whereNull('factory_id')
            ->update(['grant' => 'w', 'updated_at' => now()]);
    }

    public function down()
    {
        $adminRoleId = DB::table('roles')->where('role_code', 'Admin')->value('id');
        $factoriesScreenId = DB::table('screens')->where('screen_code', 'ie/factories')->value('id');

        DB::table('permissions')
            ->where('role_id', $adminRoleId)
            ->where('screen_id', $factoriesScreenId)
            ->whereNull('factory_id')
            ->update(['grant' => null, 'updated_at' => now()]);
    }
};
