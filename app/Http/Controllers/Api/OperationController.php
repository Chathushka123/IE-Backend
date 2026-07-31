<?php

namespace App\Http\Controllers\Api;

use App\Exports\OperationsExport;
use App\Http\Controllers\Controller;
use App\Http\Repositories\OperationRepository;
use App\Operation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class OperationController extends Controller
{
    public function export()
    {
        return Excel::download(new OperationsExport(), 'Operations_' . date('Y_m_d_H_i_s') . '.xlsx');
    }

    private function withRelations()
    {
        return ['baseOperation', 'productCategory', 'machineType', 'grade'];
    }

    public function index()
    {
        try {
            $records = Operation::with($this->withRelations())
                ->orderBy('base_operation_id')
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
            $record = Operation::with($this->withRelations())->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByOperation($operationId)
    {
        try {
            $records = Operation::with(['productCategory', 'machineType', 'grade'])
                ->where('base_operation_id', $operationId)
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
            $records = Operation::with(['baseOperation', 'productCategory', 'machineType'])
                ->where('grade_id', $gradeId)
                ->orderBy('base_operation_id')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByProductCategory($productCategoryId)
    {
        try {
            $records = Operation::with(['baseOperation', 'machineType', 'grade'])
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
            'ids.*'                 => 'integer|exists:operations,id',
        ]);

        try {
            DB::beginTransaction();
            $records = OperationRepository::resequence(
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
            $record = OperationRepository::createRec($request->all());
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
            $record = OperationRepository::updateRec($id, $request->all());
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
