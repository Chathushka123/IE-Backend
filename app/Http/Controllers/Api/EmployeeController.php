<?php

namespace App\Http\Controllers\Api;

use App\Exports\EmployeesExport;
use App\Http\Controllers\Controller;
use App\Http\Repositories\EmployeeDashboardRepository;
use App\Http\Repositories\EmployeeRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class EmployeeController extends Controller
{
    public function export()
    {
        return Excel::download(new EmployeesExport(), 'Employees_' . date('Y_m_d_H_i_s') . '.xlsx');
    }

    public function show($id)
    {
        try {
            $employee = \App\Employee::with([
                'category',
                'department',
                'designation',
                'reportingManager:id,employee_no,full_name,first_name,last_name',
                'productionLine',
                'baseLine',
            ])->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $employee], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function dashboard(Request $request)
    {
        try {
            $data = EmployeeDashboardRepository::getDashboardData(
                $request->query('from_date'),
                $request->query('to_date')
            );
            return response()->json(['status' => 'success', 'data' => $data], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $employee = EmployeeRepository::createRec($request->all());
            DB::commit();
            return response()->json(['status' => 'success', 'data' => $employee], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $employee = EmployeeRepository::updateRec($id, $request->all());
            DB::commit();
            return response()->json(['status' => 'success', 'data' => $employee], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
