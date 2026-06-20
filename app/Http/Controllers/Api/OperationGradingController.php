<?php

namespace App\Http\Controllers\Api;

use App\Exports\OperationGradingsExport;
use App\Http\Controllers\Controller;
use App\Http\Repositories\OperationGradingRepository;
use App\OperationGrading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class OperationGradingController extends Controller
{
    public function export()
    {
        return Excel::download(new OperationGradingsExport(), 'OperationGradings_' . date('Y_m_d_H_i_s') . '.xlsx');
    }

    private function withRelations()
    {
        return ['operation', 'productCategory', 'grade'];
    }

    public function index()
    {
        try {
            $records = OperationGrading::with($this->withRelations())
                ->orderBy('operation_id')
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
            $record = OperationGrading::with($this->withRelations())->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByOperation($operationId)
    {
        try {
            $records = OperationGrading::with(['productCategory', 'grade'])
                ->where('operation_id', $operationId)
                ->orderBy('sequence_no')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByGrade($gradeId)
    {
        try {
            $records = OperationGrading::with(['operation', 'productCategory'])
                ->where('grade_id', $gradeId)
                ->orderBy('operation_id')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByProductCategory($productCategoryId)
    {
        try {
            $records = OperationGrading::with(['operation', 'grade'])
                ->where('product_category_id', $productCategoryId)
                ->orderBy('sequence_no')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function resequence(Request $request)
    {
        $request->validate([
            'product_category_id'   => 'required|integer|exists:product_categories,id',
            'ids'                   => 'required|array|min:1',
            'ids.*'                 => 'integer|exists:operation_gradings,id',
        ]);

        try {
            DB::beginTransaction();
            $records = OperationGradingRepository::resequence(
                $request->product_category_id,
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
            $record = OperationGradingRepository::createRec($request->all());
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
            $record = OperationGradingRepository::updateRec($id, $request->all());
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
