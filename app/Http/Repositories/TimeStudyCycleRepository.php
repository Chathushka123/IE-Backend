<?php

namespace App\Http\Repositories;

use App\Exceptions\GeneralException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A combined cycle: one operator timed on two or more operations one after another (A, B, A, B, ...) in a
 * single stopwatch pass. The browser splits the laps by operation; each operation is then saved as an ordinary
 * study (the same rules, SMV, quality score, status, plan pair and skill-matrix effect as any other), and the
 * studies share a group id and keep their order in the cycle.
 *
 * ADR: one study per operation instead of a new "cycle study" record, so nothing downstream (review, skill
 * matrix, gap analysis, reports, SMV) has to learn about cycles. All or nothing: if any operation's study is
 * refused, none is saved.
 */
class TimeStudyCycleRepository
{
    public const MIN_OPERATIONS = 2;
    public const MAX_OPERATIONS = 6;

    /**
     * @return array{cycle_group_id:string,studies:array<int,array{id:int,operation_id:int,position:int,status:string,quality_score:?int}>}
     */
    public static function create(array $rec): array
    {
        $operations = $rec['operations'] ?? null;
        if (!is_array($operations) || count($operations) < self::MIN_OPERATIONS || count($operations) > self::MAX_OPERATIONS) {
            throw new GeneralException('A cycle needs between ' . self::MIN_OPERATIONS . ' and ' . self::MAX_OPERATIONS . ' operations.');
        }
        $operationIds = array_map(fn ($op) => (int) ($op['operation_id'] ?? 0), $operations);
        if (count(array_unique($operationIds)) !== count($operationIds) || in_array(0, $operationIds, true)) {
            throw new GeneralException('Each operation can appear once in a cycle.');
        }

        $labels = DB::table('operations')->whereIn('id', $operationIds)->get(['id', 'code', 'description'])
            ->mapWithKeys(fn ($o) => [(int) $o->id => $o->code ? "{$o->code} — {$o->description}" : (string) $o->description])->all();

        // The fields every operation's study shares. Each study is then validated by the normal study rules.
        $shared = array_intersect_key($rec, array_flip(['factory_id', 'study_date', 'time_study_type', 'style_id', 'employee_id', 'conducted_by_id']));

        $planIds = [];
        foreach ($operations as $op) {
            if (!empty($op['time_study_plan_item_id'])) {
                $planIds[] = DB::table('time_study_plan_items')->where('id', (int) $op['time_study_plan_item_id'])->value('time_study_plan_id');
            }
        }
        if (count(array_unique($planIds)) > 1) {
            throw new GeneralException('Every operation in a cycle must belong to the same plan.');
        }

        $groupId = (string) Str::uuid();

        return DB::transaction(function () use ($operations, $shared, $labels, $groupId) {
            $studies = [];
            foreach ($operations as $index => $op) {
                $operationId = (int) $op['operation_id'];
                if (empty($op['laps']) || !is_array($op['laps'])) {
                    throw new GeneralException("{$labels[$operationId]}: no laps were timed.");
                }

                $study = $shared + array_intersect_key($op, array_flip([
                    'operation_id', 'template_version_id', 'template_overridden', 'time_study_plan_item_id', 'field_values', 'skill_ids',
                    'total_down_time_ms', 'total_hold_ms', 'total_cycle_ms', 'laps', 'downtimes',
                ]));
                $study['template_version_id'] = $study['template_version_id'] ?? self::templateVersionFor($operationId, $shared['time_study_type'] ?? null);

                try {
                    $saved = TimeStudyRepository::createRec($study, ['cycle_group_id' => $groupId, 'cycle_position' => $index + 1]);
                } catch (\Exception $e) {
                    // Say which operation, so the analyst knows what to fix in a cycle of several.
                    throw new GeneralException("{$labels[$operationId]}: " . self::plainMessage($e->getMessage()));
                }

                $studies[] = [
                    'id' => (int) $saved->id,
                    'operation_id' => $operationId,
                    'position' => $index + 1,
                    'status' => $saved->status,
                    'quality_score' => $saved->quality_score !== null ? (int) $saved->quality_score : null,
                ];
            }

            return ['cycle_group_id' => $groupId, 'studies' => $studies];
        });
    }

    /** GeneralException messages are JSON-wrapped ({"err":[...]}); unwrap one so it can be prefixed and wrapped once. */
    private static function plainMessage(string $message): string
    {
        $decoded = json_decode($message, true);

        return is_array($decoded) && isset($decoded['err']) && is_array($decoded['err']) ? implode(' ', $decoded['err']) : $message;
    }

    /** The template the server would pick for this operation on its own, as the study screen does. */
    private static function templateVersionFor(int $operationId, ?string $type): int
    {
        $resolved = TimeStudyTemplateRepository::resolve($operationId, 'perf', $type);
        $chosen = $resolved['chosen'] ?? $resolved['fallback'] ?? null;
        if ($chosen === null) {
            throw new GeneralException('No template applies to this operation.');
        }

        return (int) $chosen['version_id'];
    }
}
