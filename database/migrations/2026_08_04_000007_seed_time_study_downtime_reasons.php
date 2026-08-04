<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $reasons = [
        ['name' => 'Checking', 'code' => 'CHECKING'],
        ['name' => 'Thread Break', 'code' => 'THREAD_BREAK'],
        ['name' => 'Bobbin Change', 'code' => 'BOBBIN_CHANGE'],
        ['name' => 'Bundle Handling', 'code' => 'BUNDLE_HANDLING'],
        ['name' => 'Record & Tick', 'code' => 'RECORD_AND_TICK'],
        ['name' => 'Repair', 'code' => 'REPAIR'],
        ['name' => 'Instructions', 'code' => 'INSTRUCTIONS'],
        ['name' => 'Machine Breakdown', 'code' => 'MACHINE_BREAKDOWN'],
        ['name' => 'Waiting For Work', 'code' => 'WAITING_FOR_WORK'],
        ['name' => 'Others', 'code' => 'OTHERS'],
    ];

    public function up()
    {
        $now = now();
        DB::table('time_study_downtime_reasons')->insert(
            collect($this->reasons)->map(fn ($reason) => [
                'name' => $reason['name'],
                'code' => $reason['code'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down()
    {
        DB::table('time_study_downtime_reasons')
            ->whereIn('code', collect($this->reasons)->pluck('code'))
            ->delete();
    }
};
