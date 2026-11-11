<?php

namespace App\Http\Repositories;

use App\Exceptions\GeneralException;
use App\Operation;
use App\StyleOperation;
use App\Support\TimeStudy\SmvSession;
use App\TimeStudy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * What an SMV study proposes, and applying it to the operation's SMV or to the style's own override.
 *
 * Applying is deliberate and traceable: only an approved session can be applied, only once, and every
 * application is written to operation_smv_history with the SMV it replaced.
 */
class OperationSmvRepository
{
    public const TARGETS = ['operation', 'style_operation'];

    public static function proposal(int $studyId): array
    {
        $lead = self::sessionRow($studyId);
        $rows = TimeStudy::with('employee')->withCount(['laps as laps_counted' => fn ($q) => $q->where('is_excluded', false)])
            ->where('session_id', $lead->session_id)->orderBy('id')->get();

        $runs = $rows->map(function ($row) {
            $avg = $row->avg_cycle_ms;
            $rating = $row->rating_pct !== null ? (float) $row->rating_pct : 100.0;

            return [
                'study_id' => $row->id,
                'employee_id' => $row->employee_id,
                'employee_name' => $row->employee ? ($row->employee->full_name ?: trim($row->employee->first_name . ' ' . $row->employee->last_name)) : null,
                'employee_no' => $row->employee?->employee_no,
                'rating_pct' => $row->rating_pct !== null ? (float) $row->rating_pct : null,
                'avg_cycle_ms' => $avg,
                'laps_counted' => (int) $row->laps_counted,
                'quality_score' => $row->quality_score,
                'normal_minutes' => $avg !== null ? $avg / 60000 * $rating / 100 : null,
            ];
        })->values()->all();

        $version = $lead->templateVersion;
        $allowance = (float) ($version?->definition['allowance_pct'] ?? 0);
        $operationSmv = DB::table('operations')->where('id', $lead->operation_id)->value('smv');
        $styleOperation = $lead->style_id
            ? DB::table('style_operations')->where('style_id', $lead->style_id)->where('operation_id', $lead->operation_id)->first(['id', 'smv'])
            : null;
        $history = DB::table('operation_smv_history as h')->leftJoin('users as u', 'u.id', '=', 'h.applied_by_id')
            ->where('h.source_session_id', $lead->session_id)
            ->first(['h.id', 'h.style_operation_id', 'h.old_smv', 'h.new_smv', 'h.applied_at', 'u.name as applied_by_name']);

        $normals = array_values(array_filter(array_column($runs, 'normal_minutes'), fn ($n) => $n !== null));

        return [
            'session_id' => $lead->session_id,
            'status' => $lead->status,
            'operation_id' => $lead->operation_id,
            'style_id' => $lead->style_id,
            'allowance_pct' => $allowance,
            'proposed_smv' => $lead->proposed_smv !== null ? (float) $lead->proposed_smv : null,
            'summary' => SmvSession::propose($normals, $allowance, $operationSmv !== null ? (float) $operationSmv : null),
            'runs' => $runs,
            'current' => [
                'operation_smv' => $operationSmv !== null ? (float) $operationSmv : null,
                'style_operation_id' => $styleOperation?->id,
                'style_operation_smv' => $styleOperation && $styleOperation->smv !== null ? (float) $styleOperation->smv : null,
            ],
            'default_target' => $styleOperation ? 'style_operation' : 'operation',
            'applied' => $history ? [
                'target' => $history->style_operation_id ? 'style_operation' : 'operation',
                'old_smv' => $history->old_smv !== null ? (float) $history->old_smv : null,
                'new_smv' => (float) $history->new_smv,
                'applied_at' => $history->applied_at,
                'applied_by_name' => $history->applied_by_name,
            ] : null,
        ];
    }

    public static function apply(int $studyId, ?string $target): array
    {
        $lead = self::sessionRow($studyId);

        DB::transaction(function () use ($lead, $target) {
            // Lock the session's lead row so the same session cannot be applied twice at once.
            $lead = TimeStudy::whereKey($lead->id)->lockForUpdate()->firstOrFail();

            if ($lead->status !== 'approved') {
                throw new GeneralException('Approve the study before applying its SMV.');
            }
            if ($lead->proposed_smv === null) {
                throw new GeneralException('This study has no proposed SMV to apply.');
            }
            if (DB::table('operation_smv_history')->where('source_session_id', $lead->session_id)->exists()) {
                throw new GeneralException('This SMV has already been applied.');
            }

            $styleOperation = $lead->style_id
                ? StyleOperation::where('style_id', $lead->style_id)->where('operation_id', $lead->operation_id)->first()
                : null;
            $target = $target ?: ($styleOperation ? 'style_operation' : 'operation');
            if (!in_array($target, self::TARGETS, true)) {
                throw new GeneralException('Choose whether to change the operation\'s SMV or the style\'s SMV.');
            }

            $new = (float) $lead->proposed_smv;
            if ($target === 'style_operation') {
                if (!$styleOperation) {
                    throw new GeneralException('This style has no entry for this operation, so there is no style SMV to change.');
                }
                $old = $styleOperation->smv;
                $styleOperation->update(['smv' => $new]);
            } else {
                $operation = Operation::findOrFail($lead->operation_id);
                $old = $operation->smv;
                $operation->update(['smv' => $new]);
            }

            DB::table('operation_smv_history')->insert([
                'operation_id' => $lead->operation_id,
                'style_operation_id' => $target === 'style_operation' ? $styleOperation->id : null,
                'old_smv' => $old,
                'new_smv' => $new,
                'source_session_id' => $lead->session_id,
                'applied_by_id' => Auth::id(),
                'applied_at' => now(),
            ]);
        });

        return self::proposal($studyId);
    }

    /** The session's first row, found from any row of the session. Factory scoping applies. */
    private static function sessionRow(int $studyId): TimeStudy
    {
        $study = TimeStudy::with('templateVersion')->findOrFail($studyId);
        if ($study->session_id === null || $study->purpose !== 'smv') {
            throw new GeneralException('This study is not an SMV study.');
        }

        return TimeStudy::with('templateVersion')->where('session_id', $study->session_id)->where('session_lead', true)->firstOrFail();
    }
}
