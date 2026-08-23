<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\GapAnalysisRepository;
use Exception;
use Illuminate\Http\Request;

/**
 * Gap Analysis — live-computed Team Plan x Operation required-vs-qualified
 * headcount matrix. Unlike Skill Matrix Insights, there is no "recalculate &
 * save" step here: every request recomputes fresh against the latest saved
 * Skill Matrix Insights run (see GapAnalysisRepository's docblock).
 */
class GapAnalysisController extends Controller
{
    public function matrix(Request $request)
    {
        try {
            $filters = $request->validate([
                'team_ids' => 'nullable|array',
                'team_ids.*' => 'integer|exists:teams,id',
                'product_ids' => 'nullable|array',
                'product_ids.*' => 'integer|exists:products,id',
                'statuses' => 'nullable|array',
                'statuses.*' => 'in:planned,in_progress,completed,on_hold,cancelled',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
            ]);

            $data = GapAnalysisRepository::getMatrix($filters);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function cell(Request $request)
    {
        try {
            $request->validate([
                'team_plan_id' => 'required|integer|exists:team_plans,id',
                'operation_id' => 'required|integer|exists:operations,id',
            ]);

            $data = GapAnalysisRepository::getCellDetail(
                (int) $request->query('team_plan_id'),
                (int) $request->query('operation_id')
            );

            return response()->json(['status' => 'success', 'data' => $data], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
