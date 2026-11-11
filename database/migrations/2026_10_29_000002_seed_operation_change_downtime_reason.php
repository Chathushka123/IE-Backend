<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The time spent moving from one operation to the next in a combined cycle. Recorded as down time on the
// lap of the operation being moved to, so it is taken out of that operation's cycle time instead of
// inflating it. Idempotent: databases that already have the reason are left alone.
return new class extends Migration
{
    private const CODE = 'OPERATION_CHANGE';

    public function up()
    {
        if (DB::table('time_study_downtime_reasons')->where('code', self::CODE)->exists()) {
            return;
        }
        DB::table('time_study_downtime_reasons')->insert([
            'code' => self::CODE,
            'name' => 'Operation Change',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('time_study_downtime_reasons')->where('code', self::CODE)->delete();
    }
};
