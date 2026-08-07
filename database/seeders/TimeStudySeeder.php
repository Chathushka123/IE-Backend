<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo dataset for the Time Study module so every Insights chart has data to
 * render immediately (recent-heavy so the report's default "last 7 days"
 * filter is populated, plus a 12-month tail so the Efficiency Trend line and
 * wider report filters have history too).
 *
 * Safe to re-run — always appends new studies on top of whatever exists.
 *
 * Run: php artisan db:seed --class="Database\Seeders\TimeStudySeeder"
 */
class TimeStudySeeder extends Seeder
{
    private const RECORD_COUNT = 500;

    public function run(): void
    {
        $this->ensureFactoryThreeHasEmployees();

        $employees = DB::table('employees')->select('id', 'factory_id')->get();
        $operations = DB::table('operations')
            ->select('id', 'product_category_id', 'machine_type_id', 'smv')
            ->whereNotNull('product_category_id')
            ->whereNotNull('machine_type_id')
            ->get();
        $downtimeReasonIds = DB::table('time_study_downtime_reasons')->pluck('id')->all();
        $productOperations = DB::table('product_operations')
            ->select('product_id', 'operation_id', 'smv')
            ->get()
            ->groupBy('operation_id');
        $userId = DB::table('users')->value('id');

        if ($employees->isEmpty() || $operations->isEmpty()) {
            $this->command->warn('No employees or operations found — skipping Time Study seed.');
            return;
        }

        // Stable per-operator skill factor so "Operators Needing Attention" /
        // "Most Versatile Operators" charts show a believable, consistent spread
        // rather than pure noise.
        $employeeSkill = [];
        foreach ($employees as $e) {
            $employeeSkill[$e->id] = mt_rand(80, 120) / 100; // 0.80–1.20
        }

        $now = Carbon::now();
        $pendingDowntimes = [];
        $inserted = 0;

        for ($i = 0; $i < self::RECORD_COUNT; $i++) {
            $employee = $employees->random();
            $operation = $operations->random();

            $smv = (float) $operation->smv;
            $productId = null;

            if (mt_rand(1, 100) <= 35 && $productOperations->has($operation->id)) {
                $po = $productOperations->get($operation->id)->random();
                $productId = $po->product_id;
                if ($po->smv !== null) {
                    $smv = (float) $po->smv;
                }
            }

            // 45% land in the last 10 days (keeps the report's default filter populated),
            // the rest spread across the trailing 12 months (for trend/history views).
            $studyDate = mt_rand(1, 100) <= 45
                ? $now->copy()->subDays(mt_rand(0, 10))
                : $now->copy()->subDays(mt_rand(11, 365));

            $timeStudyType = mt_rand(1, 100) <= 75 ? 'production_floor' : 'interview_training';

            $skill = $employeeSkill[$employee->id];
            $efficiencyPct = max(45, min(135, round($skill * mt_rand(85, 115), 2)));

            $lapCount = mt_rand(8, 20);
            $smvMs = max($smv, 0.1) * 60 * 1000;
            $totalProductiveMs = (int) round(($smvMs * $lapCount) / ($efficiencyPct / 100));
            $avgCycleMs = (int) round($totalProductiveMs / $lapCount);
            $fastestCycleMs = (int) round($avgCycleMs * (mt_rand(70, 90) / 100));
            $slowestCycleMs = (int) round($avgCycleMs * (mt_rand(110, 140) / 100));

            $totalDownTimeMs = mt_rand(1, 100) <= 70
                ? (int) round($totalProductiveMs * (mt_rand(0, 15) / 100))
                : 0;
            $totalHoldMs = mt_rand(1, 100) <= 30 ? mt_rand(0, 60000) : 0;
            $totalCycleMs = $totalProductiveMs + $totalDownTimeMs;

            $timeStudyId = DB::table('time_studies')->insertGetId([
                'factory_id' => $employee->factory_id,
                'study_date' => $studyDate->toDateString(),
                'time_study_type' => $timeStudyType,
                'operation_id' => $operation->id,
                'product_id' => $productId,
                'employee_id' => $employee->id,
                'product_category_id' => $operation->product_category_id,
                'machine_type_id' => $operation->machine_type_id,
                'smv' => round($smv, 4),
                'total_productive_ms' => $totalProductiveMs,
                'total_down_time_ms' => $totalDownTimeMs,
                'total_hold_ms' => $totalHoldMs,
                'total_cycle_ms' => $totalCycleMs,
                'avg_cycle_ms' => $avgCycleMs,
                'fastest_cycle_ms' => $fastestCycleMs,
                'slowest_cycle_ms' => $slowestCycleMs,
                'efficiency_pct' => $efficiencyPct,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
                'created_at' => $studyDate,
                'updated_at' => $studyDate,
            ]);

            if ($totalDownTimeMs > 0 && !empty($downtimeReasonIds)) {
                $entryCount = mt_rand(1, 3);
                $remaining = $totalDownTimeMs;
                for ($d = 0; $d < $entryCount; $d++) {
                    $share = $d === $entryCount - 1
                        ? $remaining
                        : (int) round($remaining / ($entryCount - $d) * (mt_rand(60, 140) / 100));
                    $share = max(1, min($share, $remaining));
                    $remaining -= $share;

                    $pendingDowntimes[] = [
                        'time_study_id' => $timeStudyId,
                        'lap_no' => null,
                        'time_study_downtime_reason_id' => $downtimeReasonIds[array_rand($downtimeReasonIds)],
                        'note' => null,
                        'duration_ms' => $share,
                        'created_at' => $studyDate,
                        'updated_at' => $studyDate,
                    ];

                    if ($remaining <= 0) {
                        break;
                    }
                }
            }

            $inserted++;

            // Flush downtime rows in batches to keep memory flat over 500 iterations.
            if (count($pendingDowntimes) >= 200) {
                DB::table('time_study_downtimes')->insert($pendingDowntimes);
                $pendingDowntimes = [];
            }
        }

        if (!empty($pendingDowntimes)) {
            DB::table('time_study_downtimes')->insert($pendingDowntimes);
        }

        $this->command->info("Time Study seed complete: {$inserted} studies inserted.");
    }

    /**
     * The demo dataset needs a believable operator pool in every factory so the
     * Factory multi-select filter and per-operator charts aren't lopsided.
     */
    private function ensureFactoryThreeHasEmployees(): void
    {
        $existing = DB::table('employees')->where('factory_id', 3)->count();
        if ($existing >= 8) {
            return;
        }

        $categoryId = DB::table('employees')->whereNotNull('category_id')->value('category_id');
        $userId = DB::table('users')->value('id');
        $names = [
            ['Kasun', 'Fernando'], ['Dilani', 'Silva'], ['Ruwan', 'Jayasinghe'], ['Ishara', 'Gunawardena'],
            ['Tharindu', 'Bandara'], ['Nadeesha', 'Rathnayake'], ['Chamara', 'Wickramasinghe'], ['Sanduni', 'Kumari'],
            ['Lahiru', 'Abeysekera'], ['Piyumi', 'Dissanayake'],
        ];

        $toCreate = 8 - $existing;
        $now = Carbon::now();
        $rows = [];
        for ($i = 0; $i < $toCreate; $i++) {
            [$first, $last] = $names[$i % count($names)];
            $suffix = str_pad((string) ($existing + $i + 1), 3, '0', STR_PAD_LEFT);
            $rows[] = [
                'factory_id' => 3,
                'employee_no' => "EMP-F3-{$suffix}",
                'identification_no' => strtoupper(uniqid('F3')),
                'full_name' => "{$first} {$last}",
                'first_name' => $first,
                'last_name' => $last,
                'gender' => $i % 2 === 0 ? 'Male' : 'Female',
                'category_id' => $categoryId,
                'employment_type' => 'Permanent',
                'employee_status' => 'Active',
                'joining_date' => $now->copy()->subMonths(mt_rand(1, 24))->toDateString(),
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('employees')->insert($rows);
        }
    }
}
