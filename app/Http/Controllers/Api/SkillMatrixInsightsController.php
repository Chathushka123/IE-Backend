<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\SkillMatrixCalculationRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Skill Matrix Insights — a net-new, "recalculate & save" precomputed sibling
 * of the live Skill Matrix screen (TimeStudyController@skillMatrix). Kept as
 * its own controller/repository so the live screen's protected code path is
 * never touched (hard constraint — see the approved implementation plan).
 */
class SkillMatrixInsightsController extends Controller
{
    public function latest(Request $request)
    {
        try {
            $data = SkillMatrixCalculationRepository::getLatest();

            return response()->json(['status' => 'success', 'data' => $data], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function recalculate(Request $request)
    {
        try {
            $run = SkillMatrixCalculationRepository::recalculate($request->all(), Auth::id());
            $data = SkillMatrixCalculationRepository::getLatest();

            return response()->json(['status' => 'success', 'data' => $data ?? ['run' => $run]], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function cell(Request $request)
    {
        try {
            $data = SkillMatrixCalculationRepository::getCellDetail(
                (int) $request->query('employee_id'),
                (int) $request->query('operation_id')
            );

            return response()->json(['status' => 'success', 'data' => $data], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
