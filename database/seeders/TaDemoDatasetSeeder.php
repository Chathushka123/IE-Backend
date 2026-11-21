<?php

namespace Database\Seeders;

use App\TaTemplateAction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demo Style T&A (time & action) data on the styles that already exist, so the Style T&A tab, the
 * T&A Tracker, Suggest dates, Excel export/import and the duplicate wizard all have something real
 * to show. Nothing is created in the style/customer/employee tables: it attaches to existing styles
 * and reads owners from existing designations and employees.
 *
 * Each style gets a different scenario (see SCENARIOS), dated relative to today, so the Tracker has a
 * real mix of Done / Overdue / Due in 7 days / Upcoming / Not scheduled, and the empty state and the
 * "Add missing standard actions" button can both be tried.
 *
 * Runs TaDemoOwnersSeeder first, which gives the designations used by the standard list at least two
 * active employees each (existing employees without a designation, else new "TADEMO-" demo employees).
 *
 * Needs: the standard T&A list (loaded by migration 2026_11_15_000003) and at least one style.
 * Never touches a style that already has T&A actions entered by a user.
 *
 * Demo rows are the ones with no author (created_by_id IS NULL — rows entered in the app always carry
 * one), which is how remove() and a re-run find them.
 *
 * Safe to re-run: removes the earlier demo rows first, then reseeds against today's date.
 * Remove only: (new \Database\Seeders\TaDemoDatasetSeeder)->remove()   (e.g. in tinker)
 * Run:         php artisan db:seed --class="Database\Seeders\TaDemoDatasetSeeder"
 *
 * DEV / TEST DATABASES ONLY — do not run against production.
 */
class TaDemoDatasetSeeder extends Seeder
{
    /** How far ahead an action counts as "due soon" — mirrors StyleTaActionRepository::DUE_SOON_DAYS. */
    private const DUE_SOON_DAYS = 7;

    /** Most designations used as owners; more adds nothing to a demo. */
    private const MAX_OWNER_DESIGNATIONS = 8;

    private const OVERDUE_REMARKS = [
        'Waiting for customer approval',
        'Fabric delayed at supplier',
        'Pattern revision in progress',
        'Pending trims confirmation',
    ];

    /**
     * One scenario per style, in order. dates=false leaves every planned date empty; done is the rule
     * for actions whose planned date has passed: all | on_time | behind | none. start/step are days
     * relative to today for the first action and between consecutive actions.
     */
    public const SCENARIOS = [
        ['key' => 'on_track', 'label' => 'On track — early actions done, next ones due soon', 'start' => -45, 'step' => 2, 'done' => 'on_time', 'phases' => null, 'custom' => 0, 'blank_times' => false, 'dates' => true],
        ['key' => 'behind', 'label' => 'Behind — a spread of overdue actions, others done late', 'start' => -60, 'step' => 2, 'done' => 'behind', 'phases' => null, 'custom' => 0, 'blank_times' => false, 'dates' => true],
        ['key' => 'just_started', 'label' => 'Just started — mostly upcoming', 'start' => -4, 'step' => 3, 'done' => 'on_time', 'phases' => null, 'custom' => 0, 'blank_times' => false, 'dates' => true],
        ['key' => 'not_scheduled', 'label' => 'Not scheduled — standard list applied, no dates yet', 'start' => 0, 'step' => 0, 'done' => 'none', 'phases' => null, 'custom' => 0, 'blank_times' => false, 'dates' => false],
        ['key' => 'completed', 'label' => 'Completed — every action done', 'start' => -150, 'step' => 2, 'done' => 'all', 'phases' => null, 'custom' => 0, 'blank_times' => false, 'dates' => true],
        ['key' => 'custom_mix', 'label' => 'Custom mix — extra custom actions, some blank process times', 'start' => -20, 'step' => 3, 'done' => 'behind', 'phases' => null, 'custom' => 3, 'blank_times' => true, 'dates' => true],
        ['key' => 'development_only', 'label' => 'Development only — Plant QCO still to be added', 'start' => -10, 'step' => 2, 'done' => 'on_time', 'phases' => ['Development'], 'custom' => 0, 'blank_times' => false, 'dates' => true],
        ['key' => 'empty', 'label' => 'Empty — no T&A yet (try "Apply standard T&A")', 'start' => 0, 'step' => 0, 'done' => 'none', 'phases' => [], 'custom' => 0, 'blank_times' => false, 'dates' => false],
    ];

    public function run(): void
    {
        $this->remove();
        if (!DB::table('ta_template_actions')->where('is_active', true)->exists()) {
            throw new RuntimeException('The standard T&A list is empty — run the migrations first (2026_11_15_000003 loads it).');
        }
        // Dev setup, in order: map the standard actions to designations (and those to departments), then make
        // sure each of those designations has real people, so the owners below — and the T&A employee
        // quick-list — are not empty. Undo the people with TaDemoOwnersSeeder::remove().
        $this->call(TaDesignationsSeeder::class);
        $this->call(TaDemoOwnersSeeder::class);

        // Loaded only now: the planner copies each action's designation, which the seeders above just set.
        $templates = $this->loadTemplates();
        $styles = $this->pickStyles(count(self::SCENARIOS));
        if (empty($styles)) {
            throw new RuntimeException('No styles without T&A found — this demo dataset attaches to existing styles.');
        }
        $owners = $this->loadOwners();
        $today = now()->toDateString();

        $summary = [];
        DB::transaction(function () use ($templates, $styles, $owners, $today, &$summary) {
            foreach ($styles as $index => $style) {
                $scenario = self::SCENARIOS[$index];
                $rows = $this->planRows($templates, $scenario, $today, $owners, $index);
                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table('style_ta_actions')->insert(array_map(fn ($r) => $r + ['style_id' => $style->id], $chunk));
                }
                $summary[] = [$style, $scenario, count($rows)];
            }
        });

        $this->report($summary, $owners);
    }

    /** Deletes the demo rows only (no author); anything entered through the app is left alone. */
    public function remove(): void
    {
        DB::table('style_ta_actions')->whereNull('created_by_id')->delete();
    }

    /** @return array<int,object{id:int,phase:string,sequence:int,name:string,designation_id:?int,process_time:?string}> */
    private function loadTemplates(): array
    {
        return DB::table('ta_template_actions')
            ->where('is_active', true)
            ->get(['id', 'phase', 'sequence', 'name', 'designation_id', 'process_time'])
            ->sortBy(fn ($t) => [TaTemplateAction::phaseRank($t->phase), $t->sequence, $t->id])
            ->values()
            ->all();
    }

    /**
     * Existing, active styles that have no T&A yet — one per customer first (so the Tracker shows several
     * customers), then topped up in id order.
     *
     * @return array<int,object{id:int,name:string,style_code:?string}>
     */
    private function pickStyles(int $count): array
    {
        $candidates = DB::table('styles')
            ->where('is_active', true)
            ->whereNotIn('id', DB::table('style_ta_actions')->select('style_id'))
            ->orderBy('id')
            ->get(['id', 'name', 'style_code', 'customer_id']);

        $picked = [];
        $seenCustomers = [];
        foreach ($candidates as $style) {
            if (count($picked) >= $count) {
                break;
            }
            if (!isset($seenCustomers[$style->customer_id])) {
                $seenCustomers[$style->customer_id] = true;
                $picked[$style->id] = $style;
            }
        }
        foreach ($candidates as $style) {
            if (count($picked) >= $count) {
                break;
            }
            if (!isset($picked[$style->id])) {
                $picked[$style->id] = $style;
            }
        }

        return array_values($picked);
    }

    /**
     * Active employees grouped by their (active) designation, biggest groups first — real people to
     * own actions, and real designation → employee pairs to try the picker's quick list with.
     *
     * @return array<int,int[]> designation_id => employee ids
     */
    private function loadOwners(): array
    {
        $rows = DB::table('employees')
            ->join('designations', 'designations.id', '=', 'employees.designation_id')
            ->where('employees.employee_status', 'Active')
            ->where('designations.is_active', true)
            ->orderBy('employees.id')
            ->get(['employees.id as employee_id', 'employees.designation_id']);

        $byDesignation = [];
        foreach ($rows as $row) {
            $byDesignation[$row->designation_id][] = (int) $row->employee_id;
        }
        uasort($byDesignation, fn ($a, $b) => count($b) <=> count($a));

        return array_map(
            fn ($ids) => array_slice($ids, 0, 12),
            array_slice($byDesignation, 0, self::MAX_OWNER_DESIGNATIONS, true)
        );
    }

    /**
     * Pure planner (no database): the style_ta_actions rows, without style_id, for one scenario.
     *
     * @param array<int,object> $templates already in display order
     * @param array<int,int[]>  $owners    designation_id => employee ids
     * @return array<int,array<string,mixed>>
     */
    public function planRows(array $templates, array $scenario, string $today, array $owners, int $styleIndex = 0): array
    {
        $included = array_values(array_filter(
            $templates,
            fn ($t) => $scenario['phases'] === null || in_array($t->phase, $scenario['phases'], true)
        ));

        $now = now();
        $designationIds = array_keys($owners);
        $rows = [];
        $nextSequence = [];

        foreach ($included as $i => $t) {
            [$designationId, $employeeId] = $this->ownerFor($i, $styleIndex, $t, $owners, $designationIds);
            [$planned, $actual, $remarks] = $this->datesFor($i, $scenario, $today);

            $rows[] = [
                'ta_template_action_id' => $t->id,
                'phase' => $t->phase,
                'sequence' => $t->sequence,
                'name' => $t->name,
                'designation_id' => $designationId,
                'employee_id' => $employeeId,
                'process_time' => ($scenario['blank_times'] && $i % 3 === 1) ? null : $t->process_time,
                'planned_date' => $planned,
                'actual_date' => $actual,
                'remarks' => $remarks,
                'created_by_id' => null, // demo marker — see remove()
                'updated_by_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $nextSequence[$t->phase] = max($nextSequence[$t->phase] ?? 0, (int) $t->sequence);
        }

        return array_merge($rows, $this->customActions($scenario, $today, $owners, $designationIds, $nextSequence, $now));
    }

    /**
     * Deterministic owner per action (no randomness, so a re-run reproduces the same data) with a
     * deliberate spread: mostly designation + one of its people; some designation only; some no owner
     * at all; some a person from a different designation (they are independent in the app).
     *
     * @return array{0:?int,1:?int} [designation_id, employee_id]
     */
    private function ownerFor(int $i, int $styleIndex, object $template, array $owners, array $designationIds): array
    {
        if (empty($designationIds)) {
            return [$template->designation_id, null];
        }
        if ($i % 17 === 5) {
            return [null, null];
        }

        $designationId = $template->designation_id ?: $designationIds[crc32($template->name) % count($designationIds)];
        $pool = $owners[$designationId] ?? [];

        if ($i % 10 === 9 || empty($pool)) {
            return [$designationId, null];
        }
        if ($i % 13 === 7 && count($designationIds) > 1) {
            $other = $designationIds[($i + 1) % count($designationIds)] === $designationId
                ? $designationIds[($i + 2) % count($designationIds)]
                : $designationIds[($i + 1) % count($designationIds)];
            $otherPool = $owners[$other] ?? [];
            if (!empty($otherPool)) {
                return [$designationId, $otherPool[($i + $styleIndex) % count($otherPool)]];
            }
        }

        return [$designationId, $pool[($i + $styleIndex) % count($pool)]];
    }

    /** @return array{0:?string,1:?string,2:?string} [planned_date, actual_date, remarks] */
    private function datesFor(int $i, array $scenario, string $today): array
    {
        if (!$scenario['dates']) {
            return [null, null, null];
        }

        $planned = date('Y-m-d', strtotime($today.' '.sprintf('%+d', $scenario['start'] + $i * $scenario['step']).' days'));
        if ($planned >= $today || $scenario['done'] === 'none') {
            return [$planned, null, null];
        }

        // Past planned date. Never report completion in the future.
        $capped = fn (int $lateDays) => min(date('Y-m-d', strtotime($planned.' +'.$lateDays.' days')), $today);

        switch ($scenario['done']) {
            case 'all':
                return [$planned, $capped($i % 4 === 0 ? 1 : 0), null];
            case 'on_time':
                return [$planned, $capped($i % 5 === 0 ? 1 : 0), null];
            case 'behind':
                if ($i % 4 === 3) {
                    // Still open although it is past due: the Tracker's Overdue bucket.
                    return [$planned, null, self::OVERDUE_REMARKS[intdiv($i, 4) % count(self::OVERDUE_REMARKS)]];
                }
                $late = 1 + $i % 4;

                return [$planned, $capped($late), $late > 2 ? "Completed {$late} days late" : null];
        }

        return [$planned, null, null];
    }

    /** Extra actions that are not in the standard list — what a planner adds for one style's quirks. */
    private function customActions(array $scenario, string $today, array $owners, array $designationIds, array $nextSequence, $now): array
    {
        if ($scenario['custom'] <= 0) {
            return [];
        }

        $defs = [
            ['Development', 'Customer Visit Prep', 'Presign off +4day', 5],
            ['Development', 'Fit Session With Buyer', '2', 9],
            ['Plant QCO', 'Special Machine Trial', 'Presign off +6day', 14],
        ];

        $rows = [];
        foreach (array_slice($defs, 0, $scenario['custom']) as $n => [$phase, $name, $processTime, $daysAhead]) {
            $designationId = $designationIds[$n % max(count($designationIds), 1)] ?? null;
            $pool = $designationId ? ($owners[$designationId] ?? []) : [];
            $nextSequence[$phase] = ($nextSequence[$phase] ?? 0) + 1;

            $rows[] = [
                'ta_template_action_id' => null,
                'phase' => $phase,
                'sequence' => $nextSequence[$phase],
                'name' => $name,
                'designation_id' => $designationId,
                'employee_id' => $pool[0] ?? null,
                'process_time' => $processTime,
                'planned_date' => date('Y-m-d', strtotime($today.' +'.$daysAhead.' days')),
                'actual_date' => null,
                'remarks' => 'Custom action added for this style',
                'created_by_id' => null,
                'updated_by_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    private function report(array $summary, array $owners): void
    {
        if (!$this->command) {
            return;
        }

        $this->command->info('Style T&A demo dataset seeded (dates are relative to today):');
        foreach ($summary as [$style, $scenario, $count]) {
            $this->command->line(sprintf(
                '  %-14s %-34s %3d actions  — %s',
                $style->style_code ?: '#'.$style->id,
                mb_strimwidth($style->name, 0, 34, '…'),
                $count,
                $scenario['label']
            ));
        }
        if (count($summary) < count(self::SCENARIOS)) {
            $this->command->warn('Only '.count($summary).' style(s) without T&A were available, so the last scenarios were skipped.');
        }
        if (empty($owners)) {
            $this->command->warn('No active employees with an active designation found: actions were seeded without owners.');
        }
    }
}
