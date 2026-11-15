<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\StyleTaActionsExport;
use App\Http\Repositories\StyleTaActionRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

/**
 * A style's own T&A actions (copied from the standard list). Deletes go through the generic
 * masterDetails endpoint (StyleTaActionRepository::deleteRecs).
 */
class StyleTaActionController extends Controller
{
    public function getByStyle($styleId)
    {
        try {
            return response()->json(['status' => 'success', 'data' => StyleTaActionRepository::byStyle($styleId)], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $record = StyleTaActionRepository::createRec($request->all());
            DB::commit();

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $record = StyleTaActionRepository::updateRec($id, $request->all());
            DB::commit();

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    private const FILTER_KEYS = [
        'style_id', 'customer_id', 'season_id', 'phase', 'designation_id', 'employee_id',
        'status', 'due_from', 'due_to', 'search', 'today',
    ];

    /**
     * The Tracker: every style's actions, filtered and paginated server-side (≈70 actions × every
     * style is far too many to ship at once). meta.summary holds the status counts for the filters
     * *without* the status narrowing, so the Tracker's tiles don't change as one is selected.
     */
    public function filter(Request $request)
    {
        try {
            $filters = $request->only(self::FILTER_KEYS);
            $today = StyleTaActionRepository::resolveToday($filters['today'] ?? null);
            $page = max((int) $request->input('page', 1), 1);
            $perPage = min(max((int) $request->input('per_page', 25), 1), 200);

            $paginated = StyleTaActionRepository::trackerQuery($filters, $today)
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'data' => $paginated->items(),
                'meta' => [
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                    'summary' => StyleTaActionRepository::summary($filters, $today),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function export(Request $request)
    {
        return Excel::download(
            new StyleTaActionsExport($request->only(self::FILTER_KEYS)),
            'StyleTA_'.date('Y_m_d_H_i_s').'.xlsx'
        );
    }

    public function import(Request $request)
    {
        try {
            $request->validate(['rows' => 'required|array|min:1']);

            return response()->json([
                'status' => 'success',
                'data' => StyleTaActionRepository::importRows($request->input('rows')),
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /** Body: {style_id: number, items: [{id, planned_date}]} — the confirmed "Suggest dates" preview. */
    public function applySchedule(Request $request)
    {
        try {
            $request->validate([
                'style_id' => 'required|integer',
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|integer',
                'items.*.planned_date' => 'required|date',
            ]);

            DB::beginTransaction();
            $updated = StyleTaActionRepository::applySchedule((int) $request->input('style_id'), $request->input('items'));
            DB::commit();

            return response()->json(['status' => 'success', 'data' => ['updated' => $updated]], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /** Body: {style_id: number, template_ids?: number[]} — omit template_ids to apply every active standard action. */
    public function applyTemplate(Request $request)
    {
        try {
            $request->validate([
                'style_id' => 'required|integer',
                'template_ids' => 'nullable|array',
                'template_ids.*' => 'integer',
            ]);

            DB::beginTransaction();
            $summary = StyleTaActionRepository::applyTemplate(
                (int) $request->input('style_id'),
                $request->has('template_ids') ? $request->input('template_ids') : null
            );
            DB::commit();

            return response()->json(['status' => 'success', 'data' => $summary], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
