<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\LinePlanRepository;
use App\LinePlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class LinePlanController extends Controller
{
    private function withRelations()
    {
        return ['productionLine', 'product'];
    }

    public function index(Request $request)
    {
        try {
            $request->validate([
                'product_id'          => 'nullable|integer|exists:products,id',
                'product_category_id' => 'nullable|integer|exists:product_categories,id',
                'production_line_id'  => 'nullable|integer|exists:production_lines,id',
                'start_date'          => 'nullable|date',
                'end_date'            => 'nullable|date',
            ]);

            $query = LinePlan::with($this->withRelations())
                ->orderBy('production_line_id')
                ->orderBy('sequence_no');

            if ($request->filled('production_line_id')) {
                $query->where('production_line_id', $request->integer('production_line_id'));
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
            $record = LinePlan::with($this->withRelations())->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByProductionLine($productionLineId)
    {
        try {
            $records = LinePlan::with(['product'])
                ->where('production_line_id', $productionLineId)
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
            $records = LinePlan::with(['productionLine'])
                ->where('product_id', $productId)
                ->orderBy('production_line_id')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function suggestSchedule(Request $request)
    {
        $request->validate([
            'production_line_id' => 'required|integer|exists:production_lines,id',
            'product_id'          => 'required|integer|exists:products,id',
            'planned_quantity'    => 'required|integer|min:1',
            'start_date'          => 'nullable|date',
        ]);

        try {
            $suggestion = LinePlanRepository::suggestSchedule(
                $request->production_line_id,
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
            'production_line_id' => 'required|integer|exists:production_lines,id',
            'ids'                 => 'required|array|min:1',
            'ids.*'               => 'integer|exists:line_plans,id',
        ]);

        try {
            DB::beginTransaction();
            $records = LinePlanRepository::resequence(
                $request->production_line_id,
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
            $record = LinePlanRepository::createRec($request->all());
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
            $record = LinePlanRepository::updateRec($id, $request->all());
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
