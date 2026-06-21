<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\ProductOperationGradingRepository;
use App\ProductOperationGrading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductOperationGradingController extends Controller
{
    private function withRelations()
    {
        return ['product', 'operationGrading'];
    }

    public function index()
    {
        try {
            $records = ProductOperationGrading::with($this->withRelations())
                ->orderBy('product_id')
                ->orderBy('sequence_no')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        try {
            $record = ProductOperationGrading::with($this->withRelations())->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByProduct($productId)
    {
        try {
            $records = ProductOperationGrading::with(['operationGrading'])
                ->where('product_id', $productId)
                ->orderBy('sequence_no')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByOperationGrading($operationGradingId)
    {
        try {
            $records = ProductOperationGrading::with(['product'])
                ->where('operation_grading_id', $operationGradingId)
                ->orderBy('product_id')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function resequence(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'ids'        => 'required|array|min:1',
            'ids.*'      => 'integer|exists:product_operation_gradings,id',
        ]);

        try {
            DB::beginTransaction();
            $records = ProductOperationGradingRepository::resequence(
                $request->product_id,
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
            $record = ProductOperationGradingRepository::createRec($request->all());
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
            $record = ProductOperationGradingRepository::updateRec($id, $request->all());
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
