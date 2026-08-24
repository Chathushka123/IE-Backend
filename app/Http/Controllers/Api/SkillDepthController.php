<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\SkillDepthRepository;
use Exception;
use Illuminate\Http\Request;

/**
 * Skill Depth / Bus-Factor — factory-wide, read-only rollup of the latest
 * saved Skill Matrix Insights run. Unlike Gap Analysis, entirely independent
 * of team_plans/production scheduling.
 */
class SkillDepthController extends Controller
{
    public function report(Request $request)
    {
        try {
            $filters = $request->validate([
                // reserved for future filters — none required for v1
            ]);

            $data = SkillDepthRepository::getReport($filters);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
