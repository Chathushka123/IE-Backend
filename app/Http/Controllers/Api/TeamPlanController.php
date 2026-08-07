<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\TeamPlanRepository;
use App\TeamPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class TeamPlanController extends Controller
{
    private function withRelations()
    {
        return ['team', 'product'];
    }

    public function index(Request $request)
    {
        try {
            $request->validate([
                'product_id'          => 'nullable|integer|exists:products,id',
                'product_category_id' => 'nullable|integer|exists:product_categories,id',
                'team_id'             => 'nullable|integer|exists:teams,id',
                'start_date'          => 'nullable|date',
                'end_date'            => 'nullable|date',
            ]);

            $query = TeamPlan::with($this->withRelations())
                ->orderBy('team_id')
                ->orderBy('sequence_no');

            if ($request->filled('team_id')) {
                $query->where('team_id', $request->integer('team_id'));
            }

            if ($request->filled('product_id')) {
                $query->where('product_id', $request->integer('product_id'));
            }

            if ($request->filled('product_category_id')) {
                $categoryId = $request->integer('product_category_id');
                $query->whereHas('product', fn ($q) => $q->where('product_category_id', $categoryId));
            }

            // Overlap filter: a task is included if its planned range intersects [start_date, end_date].
            if ($request->filled('start_date')) {
                $query->where(function ($q) use ($request) {
                    $q->whereNull('planned_end_date')->orWhere('planned_end_date', '>=', $request->input('start_date'));
                });
            }

            if ($request->filled('end_date')) {
                $query->where(function ($q) use ($request) {
                    $q->whereNull('planned_start_date')->orWhere('planned_start_date', '<=', $request->input('end_date'));
                });
            }

            $records = $query->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        try {
            $record = TeamPlan::with($this->withRelations())->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByTeam($teamId)
    {
        try {
            $records = TeamPlan::with(['product'])
                ->where('team_id', $teamId)
                ->orderBy('sequence_no')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByProduct($productId)
    {
        try {
            $records = TeamPlan::with(['team'])
                ->where('product_id', $productId)
                ->orderBy('team_id')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function suggestSchedule(Request $request)
    {
        $request->validate([
            'team_id'             => 'required|integer|exists:teams,id',
            'product_id'          => 'required|integer|exists:products,id',
            'planned_quantity'    => 'required|integer|min:1',
            'start_date'          => 'nullable|date',
        ]);

        try {
            $suggestion = TeamPlanRepository::suggestSchedule(
                $request->team_id,
                $request->product_id,
                $request->planned_quantity,
                $request->start_date
            );

            return response()->json(['status' => 'success', 'data' => $suggestion], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function resequence(Request $request)
    {
        $request->validate([
            'team_id'             => 'required|integer|exists:teams,id',
            'ids'                 => 'required|array|min:1',
            'ids.*'               => 'integer|exists:team_plans,id',
        ]);

        try {
            DB::beginTransaction();
            $records = TeamPlanRepository::resequence(
                $request->team_id,
                $request->ids
            );
            DB::commit();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $record = TeamPlanRepository::createRec($request->all());
            DB::commit();

            return response()->json([
                'status' => 'success',
                'data'   => $record->load($this->withRelations()),
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $record = TeamPlanRepository::updateRec($id, $request->all());
            DB::commit();

            return response()->json([
                'status' => 'success',
                'data'   => $record->load($this->withRelations()),
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
