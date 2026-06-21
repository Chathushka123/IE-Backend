<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\OperationGradingSkillRepository;
use App\OperationGradingSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class OperationGradingSkillController extends Controller
{
    public function index()
    {
        try {
            $records = OperationGradingSkill::with('operationGrading', 'skill')
                ->orderBy('operation_grading_id')
                ->get();
            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        try {
            $record = OperationGradingSkill::with('operationGrading', 'skill')->findOrFail($id);
            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByOperationGrading($operationGradingId)
    {
        try {
            $records = OperationGradingSkill::with('skill')
                ->where('operation_grading_id', $operationGradingId)
                ->get();
            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getBySkill($skillId)
    {
        try {
            $records = OperationGradingSkill::with('operationGrading')
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
            $record = OperationGradingSkillRepository::createRec($request->all());
            DB::commit();
            return response()->json(['status' => 'success', 'data' => $record->load('operationGrading', 'skill')], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $record = OperationGradingSkillRepository::updateRec($id, $request->all());
            DB::commit();
            return response()->json(['status' => 'success', 'data' => $record->load('operationGrading', 'skill')], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
