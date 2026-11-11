<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The sidebar (PermissionRepository::getNavigator) shows a screen only when "/" . screen_code equals
// the entry's path in resources/navigator.php and the role's grant is not null. The two screens added
// by 2026_10_25_000007 and _000009 were seeded as "ie/..." with no grants, but their paths are "/ts/...",
// so they never appeared in a database built from the migrations alone. This corrects the codes and
// gives the Admin role write access, as it has on every other screen. Other roles stay ungranted, to be
// set in the Permissions screen. Idempotent: databases that were fixed by hand are left as they are.
return new class extends Migration
{
    private const CODES = [
        'ie/time-study-templates' => 'ts/time-study-templates',
        'ie/time-study-review' => 'ts/time-study-review',
    ];

    public function up()
    {
        foreach (self::CODES as $old => $new) {
            DB::table('screens')->where('screen_code', $old)->update(['screen_code' => $new, 'updated_at' => now()]);
        }

        $adminRoleId = DB::table('roles')->where('role_code', 'Admin')->value('id');
        $screenIds = DB::table('screens')->whereIn('screen_code', array_values(self::CODES))->pluck('id');
        if ($adminRoleId && $screenIds->isNotEmpty()) {
            DB::table('permissions')
                ->where('role_id', $adminRoleId)
                ->whereIn('screen_id', $screenIds)
                ->whereNull('factory_id')
                ->whereNull('grant')
                ->update(['grant' => 'w', 'updated_at' => now()]);
        }
    }

    public function down()
    {
        $adminRoleId = DB::table('roles')->where('role_code', 'Admin')->value('id');
        $screenIds = DB::table('screens')->whereIn('screen_code', array_values(self::CODES))->pluck('id');
        if ($adminRoleId && $screenIds->isNotEmpty()) {
            DB::table('permissions')->where('role_id', $adminRoleId)->whereIn('screen_id', $screenIds)->whereNull('factory_id')->update(['grant' => null, 'updated_at' => now()]);
        }
        foreach (self::CODES as $old => $new) {
            DB::table('screens')->where('screen_code', $new)->update(['screen_code' => $old, 'updated_at' => now()]);
        }
    }
};
