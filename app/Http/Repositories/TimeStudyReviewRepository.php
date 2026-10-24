<?php

namespace App\Http\Repositories;

use App\Exceptions\GeneralException;
use App\TimeStudy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A reviewer's decision on a study the template sent for review. For an SMV study, one decision
 * covers all of its operators' rows.
 *
 * Decisions are final (rejected studies are never edited and resubmitted; the analyst records a new
 * one), and approval is what lets a study reach the skill matrix: every matrix query already reads
 * only approved studies of templates that count towards it.
 */
class TimeStudyReviewRepository
{
    public const DECISIONS = ['approved', 'rejected'];

    private const NOTE_MAX_LENGTH = 1000;

    public static function decide(int $studyId, ?string $decision, ?string $note): TimeStudy
    {
        $note = $note === null ? null : trim($note);

        if (!in_array($decision, self::DECISIONS, true)) {
            throw new GeneralException('Choose whether to approve or reject the study.');
        }
        if ($decision === 'rejected' && ($note === null || $note === '')) {
            throw new GeneralException('Say why the study is rejected so the analyst knows what to fix.');
        }
        if ($note !== null && mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            throw new GeneralException('The note can be at most ' . self::NOTE_MAX_LENGTH . ' characters.');
        }

        return DB::transaction(function () use ($studyId, $decision, $note) {
            // Lock the row so two reviewers cannot both decide on the same study. The model's factory
            // scope applies, so a reviewer only reaches studies from their own factories.
            $study = TimeStudy::whereKey($studyId)->lockForUpdate()->firstOrFail();

            if ($study->status !== 'needs_review') {
                $already = $study->status === 'approved' ? 'approved' : 'rejected';
                throw new GeneralException("This study has already been {$already} and cannot be changed.");
            }

            $decided = [
                'status' => $decision,
                'reviewed_by_id' => Auth::id(),
                'reviewed_at' => now(),
                'review_note' => $note === '' ? null : $note,
            ];

            if ($study->session_id !== null) {
                // An SMV study is one decision across every operator's run.
                TimeStudy::where('session_id', $study->session_id)->where('status', 'needs_review')
                    ->update($decided + ['updated_by_id' => Auth::id(), 'updated_at' => now()]);

                return $study->refresh();
            }

            $study->update($decided);

            return $study;
        });
    }
}
