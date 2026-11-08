<?php

namespace App\Http\Repositories;

use App\Employee;
use App\Exceptions\GeneralException;
use App\Support\FactoryContext;
use App\Support\TimeStudy\PlanValidator;
use App\Support\TimeStudy\TemplateDefinition;
use App\TimeStudyPlan;
use App\TimeStudyPlanItem;
use App\TimeStudyTemplateVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Study plans: who will be timed on which operations, under one template version, shared by the
 * analysts doing the timing.
 *
 * Lifecycle: draft (being put together, may break the template's layout) -> active (running; layout
 * limits are enforced on every change) -> completed or cancelled. A plan can only start when it
 * satisfies the template's layout and scope. Progress is counted from the studies recorded against
 * each planned pair, never typed in: a rejected study is final and does not count.
 *
 * ADR: a soft claim (claimed_by_id + claimed_until, renewed while in use) instead of a hard lock, so a pair does
 * not stay stuck when someone closes the tab; chose it over a distributed lock because the cost of a
 * rare double timing is one extra study, not corrupted data.
 */
class TimeStudyPlanRepository
{
    /**
     * How long a claim lasts. Short on purpose: the study screen renews it every couple of minutes while the
     * pair is loaded, so a closed tab or a laptop put to sleep frees the pair within minutes, not half an hour.
     */
    public const CLAIM_MINUTES = 5;
    public const MAX_PAIRS = 400;
    private const TYPES = ['interview_training', 'production_floor'];

    // ---------------------------------------------------------------
    // Reading
    // ---------------------------------------------------------------

    /** Plans in the user's factories, newest first. Defaults to the ones still open (draft and active). */
    public static function search(array $filters = []): array
    {
        $statuses = !empty($filters['status']) ? (array) $filters['status'] : ['draft', 'active'];
        $plans = TimeStudyPlan::query()
            ->whereIn('status', $statuses)
            ->when(!empty($filters['factory_id']), fn ($q) => $q->where('factory_id', (int) $filters['factory_id']))
            ->when(!empty($filters['template_id']), fn ($q) => $q->whereHas('templateVersion', fn ($v) => $v->where('time_study_template_id', (int) $filters['template_id'])))
            ->when(!empty($filters['mine']), fn ($q) => $q->where('created_by_id', Auth::id()))
            ->with(['templateVersion.template', 'creator'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $items = TimeStudyPlanItem::whereIn('time_study_plan_id', $plans->pluck('id'))->get()->groupBy('time_study_plan_id');
        $counts = self::studyCounts($items->flatten()->pluck('id')->all());

        return $plans->map(function (TimeStudyPlan $plan) use ($items, $counts) {
            $progress = self::progress($plan, $items->get($plan->id, collect()), $counts);

            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'status' => $plan->status,
                'factory_id' => $plan->factory_id,
                'template_id' => $plan->templateVersion->time_study_template_id,
                'template_name' => $plan->templateVersion->template->name,
                'time_study_type' => $plan->time_study_type,
                'created_by' => $plan->creator?->name,
                'can_manage' => self::canManage($plan),
                'completed_at' => $plan->completed_at ? (string) $plan->completed_at : null,
                'updated_at' => (string) $plan->updated_at,
                'progress' => $progress,
            ];
        })->values()->all();
    }

    public static function find(int $id): array
    {
        return self::present(self::plan($id));
    }

    // ---------------------------------------------------------------
    // Who may change a plan
    // ---------------------------------------------------------------

    /**
     * The person who made a plan, or an administrator, shapes it: setup, pairs, starting, finishing, cancelling.
     * Any analyst with access to the factory may time a free pair, skip one (with a reason) and record studies:
     * that is the shared work. Roles and screen grants are still deferred, so this is the whole rule for now.
     */
    public static function canManage(TimeStudyPlan $plan): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        if ((int) $plan->created_by_id === (int) $user->id || $user->email === 'sysadmin@gmail.com') {
            return true;
        }

        return $user->role_id !== null && DB::table('roles')->where('id', $user->role_id)->value('role_code') === 'Admin';
    }

    private static function assertCanManage(TimeStudyPlan $plan): void
    {
        if (!self::canManage($plan)) {
            throw new GeneralException('Only the person who made this plan, or an administrator, can change it. You can still time any pair that is free.');
        }
    }

    // ---------------------------------------------------------------
    // Plan
    // ---------------------------------------------------------------

    /** Starts a draft plan under the template's latest published version, optionally with its first pairs. */
    public static function create(array $rec): array
    {
        $name = trim((string) ($rec['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            throw new GeneralException('Give the plan a name of up to 150 characters.');
        }
        $factoryId = (int) ($rec['factory_id'] ?? 0);
        if (!DB::table('factories')->where('id', $factoryId)->exists()) {
            throw new GeneralException('Choose the factory this plan is for.');
        }
        self::assertFactoryAccess($factoryId);

        $start = TimeStudyTemplateRepository::startFrom((int) ($rec['template_id'] ?? 0));
        $type = self::validType($rec['time_study_type'] ?? null, $start['study_types']);
        $styleId = self::validStyle($rec['style_id'] ?? null);
        $target = self::validTarget($rec['target_per_pair'] ?? 1);
        $pairs = self::validPairs($rec['pairs'] ?? [], $factoryId, $start['operation_ids']);

        return DB::transaction(function () use ($name, $factoryId, $start, $type, $styleId, $target, $pairs) {
            $plan = TimeStudyPlan::create([
                'factory_id' => $factoryId,
                'name' => $name,
                'template_version_id' => $start['version_id'],
                'time_study_type' => $type,
                'style_id' => $styleId,
                'target_per_pair' => $target,
                'status' => 'draft',
            ]);
            self::insertPairs($plan, $pairs);

            return self::present($plan->fresh());
        });
    }

    public static function update(int $id, array $rec): array
    {
        $plan = self::plan($id);
        self::assertCanManage($plan);
        self::assertOpen($plan);

        $changes = [];
        if (array_key_exists('name', $rec)) {
            $name = trim((string) $rec['name']);
            if ($name === '' || mb_strlen($name) > 150) {
                throw new GeneralException('Give the plan a name of up to 150 characters.');
            }
            $changes['name'] = $name;
        }

        $setup = array_intersect_key($rec, array_flip(['time_study_type', 'style_id', 'target_per_pair']));
        if ($setup !== []) {
            if ($plan->status !== 'draft') {
                throw new GeneralException('The study type, style and target can only be changed before the plan starts.');
            }
            $coverage = TimeStudyTemplateRepository::coverageOfVersion($plan->templateVersion);
            if (array_key_exists('time_study_type', $setup)) {
                $changes['time_study_type'] = self::validType($setup['time_study_type'], $coverage['study_types']);
            }
            if (array_key_exists('style_id', $setup)) {
                $changes['style_id'] = self::validStyle($setup['style_id']);
            }
            if (array_key_exists('target_per_pair', $setup)) {
                $changes['target_per_pair'] = self::validTarget($setup['target_per_pair']);
            }
        }

        $plan->update($changes);

        return self::present($plan->fresh());
    }

    /**
     * Starts the plan. This is where the template's layout is enforced: a plan that breaks it cannot run.
     */
    public static function activate(int $id): array
    {
        $plan = self::plan($id);
        self::assertCanManage($plan);
        if ($plan->status !== 'draft') {
            throw new GeneralException('Only a draft plan can be started.');
        }
        if ($plan->time_study_type === null) {
            throw new GeneralException('Choose the study type before starting the plan.');
        }
        if ($plan->time_study_type === 'production_floor' && $plan->style_id === null) {
            throw new GeneralException('A production-floor plan needs a style.');
        }

        $problems = self::problemsFor($plan, true);
        if ($problems !== []) {
            throw new GeneralException('This plan cannot start yet. ' . implode(' ', $problems));
        }

        $plan->update(['status' => 'active', 'activated_at' => now()]);

        return self::present($plan->fresh());
    }

    public static function complete(int $id): array
    {
        $plan = self::plan($id);
        self::assertCanManage($plan);
        if ($plan->status !== 'active') {
            throw new GeneralException('Only a running plan can be finished.');
        }
        $plan->update(['status' => 'completed', 'completed_at' => now()]);
        TimeStudyPlanItem::where('time_study_plan_id', $plan->id)->update(['claimed_by_id' => null, 'claimed_until' => null]);

        return self::present($plan->fresh());
    }

    public static function cancel(int $id): array
    {
        $plan = self::plan($id);
        self::assertCanManage($plan);
        self::assertOpen($plan);
        $plan->update(['status' => 'cancelled', 'completed_at' => now()]);
        TimeStudyPlanItem::where('time_study_plan_id', $plan->id)->update(['claimed_by_id' => null, 'claimed_until' => null]);

        return self::present($plan->fresh());
    }

    // ---------------------------------------------------------------
    // Items
    // ---------------------------------------------------------------

    /**
     * Adds operators to operations. A pair already in the plan is ignored. On a running plan the limits of
     * the layout are enforced straight away; minimums are not, because a skipped pair can leave a shortfall.
     */
    public static function addPairs(int $id, array $rawPairs): array
    {
        return DB::transaction(function () use ($id, $rawPairs) {
            $plan = self::plan($id, true);
            self::assertCanManage($plan);
            self::assertOpen($plan);
            $coverage = TimeStudyTemplateRepository::coverageOfVersion($plan->templateVersion);
            $pairs = self::validPairs($rawPairs, $plan->factory_id, $coverage['operation_ids']);

            $existing = $plan->items()->get()->map(fn ($i) => $i->operation_id . ':' . $i->employee_id)->all();
            $pairs = array_values(array_filter($pairs, fn ($p) => !in_array($p['operation_id'] . ':' . $p['employee_id'], $existing, true)));
            if (count($existing) + count($pairs) > self::MAX_PAIRS) {
                throw new GeneralException('A plan can hold at most ' . self::MAX_PAIRS . ' studies. Split it into more than one plan.');
            }

            self::insertPairs($plan, $pairs);
            if ($plan->status === 'active') {
                $problems = self::problemsFor($plan->fresh(), false);
                if ($problems !== []) {
                    throw new GeneralException('That change breaks the template\'s layout. ' . implode(' ', $problems));
                }
            }
            $plan->touch();

            return self::present($plan->fresh());
        });
    }

    /** Takes a pair out of the plan. Once a study exists for it, skip it instead so the study keeps its place. */
    public static function removePair(int $id, int $itemId): array
    {
        return DB::transaction(function () use ($id, $itemId) {
            $plan = self::plan($id, true);
            self::assertCanManage($plan);
            self::assertOpen($plan);
            $item = self::item($plan, $itemId);
            if (DB::table('time_studies')->where('time_study_plan_item_id', $item->id)->exists()) {
                throw new GeneralException('A study has already been recorded for this pair, so it cannot be removed. Skip it instead.');
            }
            $item->delete();
            $plan->touch();

            return self::present($plan->fresh());
        });
    }

    public static function skipPair(int $id, int $itemId, ?string $reason): array
    {
        return DB::transaction(function () use ($id, $itemId, $reason) {
            $plan = self::plan($id, true);
            if ($plan->status !== 'active') {
                throw new GeneralException('Pairs are skipped while a plan is running. Remove it from a draft instead.');
            }
            $reason = trim((string) $reason);
            if ($reason === '' || mb_strlen($reason) > 255) {
                throw new GeneralException('Say why it is skipped (for example, the operator is absent), in up to 255 characters.');
            }
            $item = self::item($plan, $itemId);
            $item->update(['status' => 'skipped', 'skip_reason' => $reason, 'claimed_by_id' => null, 'claimed_until' => null]);
            $plan->touch();

            return self::present($plan->fresh());
        });
    }

    public static function restorePair(int $id, int $itemId): array
    {
        return DB::transaction(function () use ($id, $itemId) {
            $plan = self::plan($id, true);
            self::assertCanManage($plan);
            self::assertOpen($plan);
            $item = self::item($plan, $itemId);
            $item->update(['status' => 'pending', 'skip_reason' => null]);
            if ($plan->status === 'active') {
                $problems = self::problemsFor($plan->fresh(), false);
                if ($problems !== []) {
                    throw new GeneralException('Bringing that back breaks the template\'s layout. ' . implode(' ', $problems));
                }
            }
            $plan->touch();

            return self::present($plan->fresh());
        });
    }

    // ---------------------------------------------------------------
    // Timing a pair
    // ---------------------------------------------------------------

    /**
     * Reserves a pair for the current analyst for a while, so two analysts do not time the same one.
     * Claiming a pair you already hold just renews it.
     */
    public static function claim(int $id, int $itemId): array
    {
        return DB::transaction(function () use ($id, $itemId) {
            $plan = self::plan($id, true);
            if ($plan->status !== 'active') {
                throw new GeneralException('This plan is not running.');
            }
            $item = TimeStudyPlanItem::where('time_study_plan_id', $plan->id)->whereKey($itemId)->lockForUpdate()->first();
            if (!$item) {
                throw new GeneralException('That pair is not in this plan.');
            }
            if ($item->status === 'skipped') {
                throw new GeneralException('That pair was skipped.');
            }
            if ((self::studyCounts([$item->id])[$item->id] ?? 0) >= $plan->target_per_pair) {
                throw new GeneralException('Every study planned for this pair is already recorded.');
            }
            $holder = self::currentHolder($item);
            if ($holder !== null && $holder->id !== Auth::id()) {
                throw new GeneralException("{$holder->name} is already timing this pair.");
            }

            $item->update(['claimed_by_id' => Auth::id(), 'claimed_until' => now()->addMinutes(self::CLAIM_MINUTES)]);

            return self::present($plan->fresh());
        });
    }

    public static function release(int $id, int $itemId): array
    {
        $plan = self::plan($id);
        $item = self::item($plan, $itemId);
        if ($item->claimed_by_id === Auth::id()) {
            $item->update(['claimed_by_id' => null, 'claimed_until' => null]);
        }

        return self::present($plan->fresh());
    }

    /**
     * Called when a study is saved for a planned pair: the study must be for that pair, under the plan's
     * template, and the pair must not be held by someone else. Returns the item so the caller can link it.
     */
    public static function assertCanRecord(int $itemId, array $rec): TimeStudyPlanItem
    {
        // Locked for the rest of the caller's transaction, so concurrent saves for one pair queue up and each
        // sees the studies the one before it recorded.
        $item = TimeStudyPlanItem::whereKey($itemId)->lockForUpdate()->first();
        $plan = $item ? TimeStudyPlan::find($item->time_study_plan_id) : null;
        if (!$item || !$plan) {
            throw new GeneralException('That planned study could not be found.');
        }
        if ($plan->status !== 'active') {
            throw new GeneralException('This plan is not running, so studies cannot be added to it.');
        }
        if ($item->status === 'skipped') {
            throw new GeneralException('That pair was skipped in the plan.');
        }
        if ((self::studyCounts([$item->id])[$item->id] ?? 0) >= $plan->target_per_pair) {
            throw new GeneralException('Every study planned for this pair is already recorded.');
        }
        if ((int) $item->operation_id !== (int) ($rec['operation_id'] ?? 0) || (int) $item->employee_id !== (int) ($rec['employee_id'] ?? 0)) {
            throw new GeneralException('This study is not for the operator and operation in the plan.');
        }
        if (isset($rec['template_version_id']) && (int) $rec['template_version_id'] !== (int) $plan->template_version_id) {
            throw new GeneralException('This plan runs under a different template version than the one chosen for the study.');
        }
        if (($rec['time_study_type'] ?? null) !== $plan->time_study_type) {
            throw new GeneralException('This plan is for ' . ($plan->time_study_type === 'production_floor' ? 'production floor' : 'interview and training') . ' studies.');
        }
        if ($plan->style_id !== null && (int) ($rec['style_id'] ?? 0) !== (int) $plan->style_id) {
            throw new GeneralException('This plan is for a specific style.');
        }
        $holder = self::currentHolder($item);
        if ($holder !== null && $holder->id !== Auth::id()) {
            throw new GeneralException("{$holder->name} is timing this pair right now.");
        }

        return $item;
    }

    /** Frees the claim once the study it was held for is saved. */
    public static function releaseAfterRecording(TimeStudyPlanItem $item): void
    {
        $item->update(['claimed_by_id' => null, 'claimed_until' => null]);
        TimeStudyPlan::whereKey($item->time_study_plan_id)->update(['updated_at' => now()]);
    }

    // ---------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------

    private static function plan(int $id, bool $lock = false): TimeStudyPlan
    {
        $query = TimeStudyPlan::query();
        if ($lock) {
            $query->lockForUpdate();
        }
        $plan = $query->with('templateVersion.template')->find($id);
        if (!$plan) {
            throw new GeneralException('That study plan could not be found.');
        }

        return $plan;
    }

    private static function item(TimeStudyPlan $plan, int $itemId): TimeStudyPlanItem
    {
        $item = TimeStudyPlanItem::where('time_study_plan_id', $plan->id)->find($itemId);
        if (!$item) {
            throw new GeneralException('That pair is not in this plan.');
        }

        return $item;
    }

    private static function assertOpen(TimeStudyPlan $plan): void
    {
        if (!in_array($plan->status, ['draft', 'active'], true)) {
            throw new GeneralException('This plan is already ' . $plan->status . ' and cannot be changed.');
        }
    }

    private static function assertFactoryAccess(int $factoryId): void
    {
        if (FactoryContext::isResolved() && !FactoryContext::isBypassed() && !in_array($factoryId, FactoryContext::ids() ?? [], true)) {
            throw new GeneralException('You do not have access to that factory.');
        }
    }

    private static function validType($type, array $allowed): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new GeneralException('Choose interview and training or production floor.');
        }
        if (!in_array($type, $allowed, true)) {
            throw new GeneralException('This template does not cover that kind of study.');
        }

        return $type;
    }

    private static function validStyle($styleId): ?int
    {
        if ($styleId === null || $styleId === '') {
            return null;
        }
        if (!DB::table('styles')->where('id', (int) $styleId)->exists()) {
            throw new GeneralException('That style could not be found.');
        }

        return (int) $styleId;
    }

    private static function validTarget($target): int
    {
        if (!is_numeric($target) || (int) $target < 1 || (int) $target > 10) {
            throw new GeneralException('Studies per operator and operation must be between 1 and 10.');
        }

        return (int) $target;
    }

    /**
     * @param array<int,array{operation_id:int,employee_id:int}> $raw
     * @param int[]|null $coverage
     * @return array<int,array{operation_id:int,employee_id:int}>
     */
    private static function validPairs($raw, int $factoryId, ?array $coverage): array
    {
        if (!is_array($raw)) {
            throw new GeneralException('The operators to plan must be a list.');
        }

        $pairs = [];
        foreach ($raw as $pair) {
            $operationId = (int) ($pair['operation_id'] ?? 0);
            $employeeId = (int) ($pair['employee_id'] ?? 0);
            if ($operationId < 1 || $employeeId < 1) {
                throw new GeneralException('Every planned study needs an operation and an operator.');
            }
            $pairs[$operationId . ':' . $employeeId] = ['operation_id' => $operationId, 'employee_id' => $employeeId];
        }
        $pairs = array_values($pairs);
        if (count($pairs) === 0) {
            return [];
        }

        $operationIds = array_values(array_unique(array_column($pairs, 'operation_id')));
        $found = DB::table('operations')->whereIn('id', $operationIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($found) !== count($operationIds)) {
            throw new GeneralException('One of the operations could not be found.');
        }
        if ($coverage !== null && array_diff($operationIds, $coverage) !== []) {
            throw new GeneralException('Only operations covered by the template can be planned.');
        }

        $employeeIds = array_values(array_unique(array_column($pairs, 'employee_id')));
        $inFactory = Employee::withoutGlobalScopes()->whereIn('id', $employeeIds)->where('factory_id', $factoryId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($inFactory) !== count($employeeIds)) {
            throw new GeneralException('Every operator must belong to the plan\'s factory.');
        }

        return $pairs;
    }

    private static function insertPairs(TimeStudyPlan $plan, array $pairs): void
    {
        foreach ($pairs as $pair) {
            TimeStudyPlanItem::create(['time_study_plan_id' => $plan->id] + $pair);
        }
    }

    /** @return string[] */
    private static function problemsFor(TimeStudyPlan $plan, bool $requireMinimums): array
    {
        $items = TimeStudyPlanItem::where('time_study_plan_id', $plan->id)->where('status', 'pending')->get();
        $pairs = $items->map(fn ($i) => ['operation_id' => (int) $i->operation_id, 'employee_id' => (int) $i->employee_id])->all();
        $coverage = TimeStudyTemplateRepository::coverageOfVersion($plan->templateVersion)['operation_ids'];

        return PlanValidator::problems(
            TemplateDefinition::layoutOf($plan->templateVersion->definition),
            $pairs,
            $coverage,
            self::names($items),
            $requireMinimums
        );
    }

    /** @return array{operations:array<int,string>,operators:array<int,string>} */
    private static function names($items): array
    {
        $operations = DB::table('operations')->whereIn('id', $items->pluck('operation_id')->unique())->get(['id', 'code', 'description'])
            ->mapWithKeys(fn ($o) => [(int) $o->id => self::operationLabel($o)])->all();
        $operators = Employee::withoutGlobalScopes()->whereIn('id', $items->pluck('employee_id')->unique())->get(['id', 'first_name', 'last_name', 'full_name'])
            ->mapWithKeys(fn ($e) => [(int) $e->id => self::employeeName($e)])->all();

        return ['operations' => $operations, 'operators' => $operators];
    }

    private static function operationLabel($operation): string
    {
        return $operation->code ? "{$operation->code} — {$operation->description}" : (string) $operation->description;
    }

    private static function employeeName($employee): string
    {
        return $employee->full_name ?: trim($employee->first_name . ' ' . $employee->last_name);
    }

    /** @return array<int,int> studies recorded per plan item, rejected ones left out (they are final and do not count) */
    private static function studyCounts(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return DB::table('time_studies')
            ->whereIn('time_study_plan_item_id', $itemIds)
            ->where('status', '!=', 'rejected')
            ->groupBy('time_study_plan_item_id')
            ->selectRaw('time_study_plan_item_id as item_id, COUNT(*) as n')
            ->pluck('n', 'item_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    private static function currentHolder(TimeStudyPlanItem $item)
    {
        if ($item->claimed_by_id === null || $item->claimed_until === null || Carbon::parse($item->claimed_until)->isPast()) {
            return null;
        }

        return DB::table('users')->where('id', $item->claimed_by_id)->first(['id', 'name']);
    }

    private static function progress(TimeStudyPlan $plan, $items, array $counts): array
    {
        $wanted = $items->where('status', 'pending');
        $done = $wanted->filter(fn ($i) => ($counts[$i->id] ?? 0) >= $plan->target_per_pair)->count();

        return [
            'pairs' => $wanted->count(),
            'pairs_done' => $done,
            'skipped' => $items->where('status', 'skipped')->count(),
            'studies_target' => $wanted->count() * $plan->target_per_pair,
            'studies_done' => $wanted->sum(fn ($i) => min($counts[$i->id] ?? 0, $plan->target_per_pair)),
        ];
    }

    private static function present(TimeStudyPlan $plan): array
    {
        $plan->loadMissing('templateVersion.template', 'creator');
        $version = $plan->templateVersion;
        $items = $plan->items()->get();
        $counts = self::studyCounts($items->pluck('id')->all());
        $coverage = TimeStudyTemplateRepository::coverageOfVersion($version);

        $operations = DB::table('operations')->whereIn('id', $items->pluck('operation_id')->unique())->get(['id', 'code', 'description'])->keyBy('id');
        $employees = Employee::withoutGlobalScopes()->whereIn('id', $items->pluck('employee_id')->unique())->get()->keyBy('id');
        $holders = DB::table('users')->whereIn('id', $items->pluck('claimed_by_id')->filter()->unique())->pluck('name', 'id');
        $style = $plan->style_id ? DB::table('styles')->where('id', $plan->style_id)->first(['id', 'name']) : null;

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'status' => $plan->status,
            'factory_id' => $plan->factory_id,
            'time_study_type' => $plan->time_study_type,
            'style' => $style ? ['id' => (int) $style->id, 'name' => $style->name] : null,
            'target_per_pair' => (int) $plan->target_per_pair,
            'created_by' => $plan->creator?->name,
            'can_manage' => self::canManage($plan),
            'completed_at' => $plan->completed_at ? (string) $plan->completed_at : null,
            // Same shape the study screen already uses for a chosen template, so a plan can be run directly.
            'template' => [
                'template_id' => $version->time_study_template_id,
                'version_id' => $version->id,
                'name' => $version->template->name,
                'version_no' => $version->version_no,
                'manual_only' => (bool) ($version->definition['manual_only'] ?? false),
                'counts_in_matrix' => (bool) ($version->definition['counts_in_matrix'] ?? false),
                'definition' => $version->definition,
            ],
            'layout' => TemplateDefinition::layoutOf($version->definition),
            'coverage' => $coverage,
            'items' => $items->map(function (TimeStudyPlanItem $item) use ($plan, $operations, $employees, $holders, $counts) {
                $employee = $employees->get($item->employee_id);
                $holderId = self::currentHolder($item)?->id;
                $studies = $counts[$item->id] ?? 0;

                return [
                    'id' => $item->id,
                    'operation_id' => (int) $item->operation_id,
                    'operation_label' => $operations->has($item->operation_id) ? self::operationLabel($operations->get($item->operation_id)) : "Operation #{$item->operation_id}",
                    'employee' => $employee ? [
                        'id' => (int) $employee->id,
                        'employee_no' => $employee->employee_no,
                        'first_name' => $employee->first_name,
                        'last_name' => $employee->last_name,
                        'full_name' => self::employeeName($employee),
                        'factory_id' => (int) $employee->factory_id,
                    ] : null,
                    'status' => $item->status,
                    'skip_reason' => $item->skip_reason,
                    'studies_done' => $studies,
                    'done' => $item->status === 'pending' && $studies >= $plan->target_per_pair,
                    'claimed_by' => $holderId ? ['id' => (int) $holderId, 'name' => $holders->get($holderId)] : null,
                    'claimed_by_me' => $holderId !== null && (int) $holderId === (int) Auth::id(),
                ];
            })->values()->all(),
            'progress' => self::progress($plan, $items, $counts),
            // Draft plans are held to the whole layout; a running plan only to its limits (see addPairs).
            'problems' => in_array($plan->status, ['draft', 'active'], true) ? self::problemsFor($plan, $plan->status === 'draft') : [],
            'updated_at' => (string) $plan->updated_at,
        ];
    }
}
