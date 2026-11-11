<?php

namespace App\Http\Repositories;

use App\Exceptions\GeneralException;
use App\Support\TimeStudy\SmvResolver;
use App\Support\TimeStudy\SmvSession;
use App\Support\TimeStudy\StudyEvaluator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * An SMV study: several operators timed on one operation, one rating each, one proposed SMV.
 *
 * It arrives as a single request so the session is saved whole or not at all. Each operator's run
 * becomes an ordinary time_studies row, checked by the same rule engine as any study; the rows share a
 * session_id and one status. The proposed SMV (SmvSession) is stored on every row. Nothing here changes
 * an SMV: that happens only when a reviewer applies an approved session (OperationSmvRepository).
 */
class TimeStudySmvSessionRepository
{
    private const MAX_RUNS = 20;

    public static function create(array $rec): array
    {
        self::validate($rec);

        $runs = $rec['runs'];
        $employeeIds = array_column($runs, 'employee_id');
        if (count($employeeIds) !== count(array_unique($employeeIds))) {
            throw new GeneralException('Each operator can be timed once per SMV study.');
        }
        foreach ($runs as $run) {
            if (count(array_filter($run['laps'], fn ($lap) => empty($lap['is_excluded']))) === 0) {
                throw new GeneralException('Every operator needs at least one lap that is included in the calculations.');
            }
            foreach ($run['laps'] as $lap) {
                if (($lap['down_time_ms'] ?? 0) > $lap['duration_ms']) {
                    throw new GeneralException('Lap ' . $lap['lap_no'] . ': down time cannot be longer than the lap itself.');
                }
            }
        }

        $version = TimeStudyRepository::resolveTemplateVersion((int) $rec['template_version_id']);
        if ($version->template->purpose !== 'smv') {
            throw new GeneralException('This template is for operator performance studies, not for establishing an SMV.');
        }
        $definition = $version->definition;

        $operation = DB::table('operations')->where('id', $rec['operation_id'])->first();
        $current = SmvResolver::resolve((int) $rec['operation_id'], $rec['style_id'] ?? null);
        $names = self::employeeNames($employeeIds);

        $evaluations = [];
        $problems = [];
        foreach ($runs as $i => $run) {
            $evaluations[$i] = StudyEvaluator::evaluate($definition, array_map(fn ($lap) => [
                'lap_no' => $lap['lap_no'],
                'duration_ms' => $lap['duration_ms'],
                'down_time_ms' => $lap['down_time_ms'] ?? 0,
                'excluded' => !empty($lap['is_excluded']),
                'outlier_kept' => !empty($lap['outlier_kept']),
            ], $run['laps']), [
                'purpose' => 'smv',
                'operators' => count($runs),
                'values' => $rec['field_values'] ?? [],
                'rating' => $run['rating_pct'] ?? null,
                'smv' => $current['smv'],
            ]);
            if ($evaluations[$i]['blockers']) {
                $problems[] = $names[$run['employee_id']] . ': ' . implode(', ', array_column($evaluations[$i]['blockers'], 'label'));
            }
        }
        if ($problems) {
            throw new GeneralException('This SMV study does not meet the template rules. ' . implode('. ', $problems) . '.');
        }

        $normals = array_map(fn ($e) => $e['smv_calc']['normal_minutes'] ?? null, $evaluations);
        if (in_array(null, $normals, true)) {
            throw new GeneralException('Every operator needs at least one timed lap.');
        }
        $proposal = SmvSession::propose($normals, (float) ($definition['allowance_pct'] ?? 0), $current['smv']);
        [$status] = SmvSession::decideStatus($definition['approval'] ?? 'always', $evaluations);

        return DB::transaction(function () use ($rec, $runs, $evaluations, $version, $operation, $current, $proposal, $status) {
            $sessionId = (string) Str::uuid();
            $ids = [];

            foreach ($runs as $i => $run) {
                $stats = $evaluations[$i]['stats'];
                $header = [
                    'factory_id' => $rec['factory_id'] ?? null,
                    'study_date' => $rec['study_date'],
                    'time_study_type' => $rec['time_study_type'],
                    'operation_id' => $rec['operation_id'],
                    'style_id' => $rec['style_id'] ?? null,
                    'employee_id' => $run['employee_id'],
                    'conducted_by_id' => $rec['conducted_by_id'] ?? Auth::id(),
                    'product_category_id' => $operation->product_category_id,
                    'machine_type_id' => $operation->machine_type_id,
                    'smv' => $current['smv'],
                    'smv_source' => $current['source'],
                    'avg_cycle_ms' => $stats['avg_cycle_ms'],
                    'fastest_cycle_ms' => $stats['fastest_cycle_ms'],
                    'slowest_cycle_ms' => $stats['slowest_cycle_ms'],
                    'efficiency_pct' => $stats['efficiency_pct'],
                    'total_down_time_ms' => $run['total_down_time_ms'],
                    'total_hold_ms' => $run['total_hold_ms'],
                    'total_cycle_ms' => $run['total_cycle_ms'],
                    'total_productive_ms' => max(0, $run['total_cycle_ms'] - $run['total_down_time_ms']),
                    'template_version_id' => $version->id,
                    'template_overridden' => !empty($rec['template_overridden']),
                    'purpose' => 'smv',
                    // An SMV study sets a standard; it is never evidence of an operator's skill level.
                    'counts_in_matrix' => false,
                    'status' => $status,
                    'quality_score' => $evaluations[$i]['quality_score'],
                    'quality_breakdown' => $evaluations[$i]['quality_breakdown'],
                    'rule_flags' => array_map(fn ($c) => ['id' => $c['id'], 'label' => $c['label']], $evaluations[$i]['warnings']),
                    'field_values' => $rec['field_values'] ?? null,
                    'session_id' => $sessionId,
                    'session_lead' => $i === 0,
                    'rating_pct' => $run['rating_pct'] ?? null,
                    'proposed_smv' => $proposal['proposed_smv'],
                ];
                $ids[] = TimeStudyRepository::persist($header, $evaluations[$i]['laps'], $run['skill_ids'] ?? [], $run['downtimes'] ?? [])->id;
            }

            return ['session_id' => $sessionId, 'status' => $status, 'study_ids' => $ids, 'proposal' => $proposal];
        });
    }

    private static function validate(array $rec): void
    {
        $validator = Validator::make($rec, [
            'factory_id' => 'nullable|integer|exists:factories,id',
            'study_date' => 'required|date',
            'time_study_type' => 'required|in:interview_training,production_floor',
            'operation_id' => 'required|integer|exists:operations,id',
            'style_id' => 'nullable|required_if:time_study_type,production_floor|integer|exists:styles,id',
            'conducted_by_id' => 'nullable|integer|exists:users,id',
            'template_version_id' => 'required|integer|exists:time_study_template_versions,id',
            'template_overridden' => 'nullable|boolean',
            'field_values' => 'nullable|array',

            'runs' => 'required|array|min:1|max:' . self::MAX_RUNS,
            'runs.*.employee_id' => 'required|integer|exists:employees,id',
            'runs.*.rating_pct' => 'nullable|numeric|min:1|max:300',
            'runs.*.total_down_time_ms' => 'required|integer|min:0',
            'runs.*.total_hold_ms' => 'required|integer|min:0',
            'runs.*.total_cycle_ms' => 'required|integer|min:0',
            'runs.*.skill_ids' => 'nullable|array',
            'runs.*.skill_ids.*' => 'integer|exists:base_skills,id',
            'runs.*.laps' => 'required|array|min:1',
            'runs.*.laps.*.lap_no' => 'required|integer|min:1',
            'runs.*.laps.*.duration_ms' => 'required|integer|min:0',
            'runs.*.laps.*.down_time_ms' => 'nullable|integer|min:0',
            'runs.*.laps.*.is_excluded' => 'nullable|boolean',
            'runs.*.laps.*.outlier_kept' => 'nullable|boolean',
            'runs.*.downtimes' => 'nullable|array',
            'runs.*.downtimes.*.lap_no' => 'nullable|integer|min:1',
            'runs.*.downtimes.*.time_study_downtime_reason_id' => 'required|integer|exists:time_study_downtime_reasons,id',
            'runs.*.downtimes.*.note' => 'nullable|string',
            'runs.*.downtimes.*.duration_ms' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            Utilities::extractError($validator);
        }
    }

    /** @return array<int,string> */
    private static function employeeNames(array $ids): array
    {
        return DB::table('employees')->whereIn('id', $ids)
            ->selectRaw("id, COALESCE(NULLIF(full_name, ''), CONCAT(first_name, ' ', last_name)) as name")
            ->pluck('name', 'id')->all();
    }
}
