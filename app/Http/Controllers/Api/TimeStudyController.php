<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\TimeStudyRepository;
use App\Http\Repositories\TimeStudyDashboardRepository;
use App\Http\Repositories\SkillMatrixRepository;
use App\Exports\TimeStudyMultiSheetExport;
use App\TimeStudy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Exception;

class TimeStudyController extends Controller
{
    private function withRelations()
    {
        return [
            'factory',
            'operation.baseOperation',
            'operation.productCategory',
            'operation.machineType',
            'product',
            'employee.department',
            'productCategory',
            'machineType',
            'softSkills',
            'laps',
            'downtimes.reason',
        ];
    }

    public function index()
    {
        try {
            $records = TimeStudy::with($this->withRelations())
                ->orderByDesc('id')
                ->paginate(20);

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        try {
            $record = TimeStudy::with($this->withRelations())->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByOperator($employeeId)
    {
        try {
            $records = TimeStudy::with($this->withRelations())
                ->where('employee_id', $employeeId)
                ->orderByDesc('study_date')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getByOperation($operationId)
    {
        try {
            $records = TimeStudy::with($this->withRelations())
                ->where('operation_id', $operationId)
                ->orderByDesc('study_date')
                ->get();

            return response()->json(['status' => 'success', 'data' => $records], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function export(Request $request)
    {
        return Excel::download(
            new TimeStudyMultiSheetExport($request->query()),
            'TimeStudies_' . date('Y_m_d_His') . '.xlsx',
            ExcelFormat::XLSX
        );
    }

    public function dashboard(Request $request)
    {
        try {
            $data = TimeStudyDashboardRepository::getDashboardData($request->query());

            return response()->json(['status' => 'success', 'data' => $data], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function skillMatrix(Request $request)
    {
        try {
            $data = SkillMatrixRepository::getMatrix($request->query());

            return response()->json(['status' => 'success', 'data' => $data], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function skillMatrixCell(Request $request)
    {
        try {
            $data = SkillMatrixRepository::getCellDetail($request->query());

            return response()->json(['status' => 'success', 'data' => $data], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $record = TimeStudyRepository::createRec($request->all());
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
