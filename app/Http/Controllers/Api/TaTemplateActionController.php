<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\TaTemplateActionsExport;
use App\Http\Repositories\TaTemplateActionRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

/**
 * The standard T&A (time & action) list that planners maintain once and copy onto each style.
 * Deletes go through the generic masterDetails endpoint (TaTemplateActionRepository::deleteRecs).
 */
class TaTemplateActionController extends Controller
{
    public function index()
    {
        try {
            return response()->json(['status' => 'success', 'data' => TaTemplateActionRepository::listAll()], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $record = TaTemplateActionRepository::createRec($request->all());
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
            $record = TaTemplateActionRepository::updateRec($id, $request->all());
            DB::commit();

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function export()
    {
        return Excel::download(new TaTemplateActionsExport(), 'TAMaster_'.date('Y_m_d_H_i_s').'.xlsx');
    }

    /** The sheet is parsed client-side into row objects (see taTemplateActions.api.ts); this applies them row by row. */
    public function import(Request $request)
    {
        try {
            $request->validate(['rows' => 'required|array|min:1']);

            return response()->json([
                'status' => 'success',
                'data' => TaTemplateActionRepository::importRows($request->input('rows')),
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /** Body: {phase: string, ids: number[]} — the phase's actions in their new order. */
    public function reorder(Request $request)
    {
        try {
            $request->validate([
                'phase' => 'required|string|max:50',
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer',
            ]);

            DB::beginTransaction();
            $records = TaTemplateActionRepository::reorder($request->input('phase'), $request->input('ids'));
            DB::commit();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
