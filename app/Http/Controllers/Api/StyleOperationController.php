<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\StyleOperationRepository;
use App\StyleOperation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class StyleOperationController extends Controller
{
    private function withRelations()
    {
        return ['style', 'operation'];
    }

    public function index()
    {
        try {
            $records = StyleOperation::with($this->withRelations())
                ->orderBy('style_id')
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
            $record = StyleOperation::with($this->withRelations())->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByStyle($styleId)
    {
        try {
            $records = StyleOperation::with(['operation.baseOperation', 'operation.machineType', 'operation.grade'])
                ->where('style_id', $styleId)
                ->orderBy('sequence_no')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByOperation($operationId)
    {
        try {
            $records = StyleOperation::with(['style'])
                ->where('operation_id', $operationId)
                ->orderBy('style_id')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function resequence(Request $request)
    {
        $request->validate([
            'style_id' => 'required|integer|exists:styles,id',
            'ids'        => 'required|array|min:1',
            'ids.*'      => 'integer|exists:style_operations,id',
        ]);

        try {
            DB::beginTransaction();
            $records = StyleOperationRepository::resequence(
                $request->style_id,
                $request->ids
            );
            DB::commit();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Copy chosen operations from another style's bulletin onto the end of this style's bulletin.
     * Body: { source_style_id, operations: [{ operation_id, smv? }] } — smv null inherits the operation master's.
     */
    public function copyFromStyle(Request $request, $styleId)
    {
        $request->validate([
            'source_style_id' => 'required|integer|exists:styles,id',
            'operations' => 'required|array|min:1|max:500',
            'operations.*.operation_id' => 'required|integer|exists:operations,id',
            'operations.*.smv' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();
            $created = StyleOperationRepository::copyFromStyle(
                (int) $styleId,
                (int) $request->source_style_id,
                $request->operations
            );
            DB::commit();

            return response()->json([
                'status' => 'success',
                'data'   => $created->load(['operation.baseOperation', 'operation.machineType', 'operation.grade'])->values(),
                'meta'   => ['operations_copied' => $created->count()],
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $record = StyleOperationRepository::createRec($request->all());
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
            $record = StyleOperationRepository::updateRec($id, $request->all());
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
