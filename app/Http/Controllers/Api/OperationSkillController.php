<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\OperationSkillRepository;
use App\OperationSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class OperationSkillController extends Controller
{
    public function index()
    {
        try {
            $records = OperationSkill::with('operation', 'skill')
                ->orderBy('operation_id')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        try {
            $record = OperationSkill::with('operation', 'skill')->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByOperation($operationId)
    {
        try {
            $records = OperationSkill::with('skill')
                ->where('operation_id', $operationId)
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getBySkill($skillId)
    {
        try {
            $records = OperationSkill::with('operation')
                ->where('skill_id', $skillId)
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $record = OperationSkillRepository::createRec($request->all());
            DB::commit();

            return response()->json(['status' => 'success', 'data' => $record->load('operation', 'skill')], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $record = OperationSkillRepository::updateRec($id, $request->all());
            DB::commit();

            return response()->json(['status' => 'success', 'data' => $record->load('operation', 'skill')], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
