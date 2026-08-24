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
    public function export(Request $request)
    {
        $filters = $request->only([
            'gender',
            'marital_status',
            'nationality',
            'religion',
            'country_id',
            'factory_id',
            'management_hierarchy_id',
            'department_id',
            'team_id',
            'employment_type',
            'employee_status',
            'reporting_manager_id',
            'employee_category',
            'birthday_from',
            'birthday_to',
            'joining_date_from',
            'joining_date_to',
            'created_at_from',
            'created_at_to',
        ]);

        return Excel::download(new EmployeesExport($filters), 'Employees_' . date('Y_m_d_H_i_s') . '.xlsx');
    }

    /**
     * Distinct values already in use for the free-text nationality/religion fields —
     * there's no fixed master list for either, so the export filter dialog's button
     * groups for them are built from whatever values employees actually have.
     */
    public function distinctValues()
    {
        try {
            $nationalities = \App\Employee::whereNotNull('nationality')
                ->where('nationality', '!=', '')
                ->distinct()
                ->orderBy('nationality')
                ->pluck('nationality');

            $religions = \App\Employee::whereNotNull('religion')
                ->where('religion', '!=', '')
                ->distinct()
                ->orderBy('religion')
                ->pluck('religion');

            return response()->json([
                'status' => 'success',
                'data' => ['nationalities' => $nationalities, 'religions' => $religions],
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * The Excel file itself is parsed client-side (via SheetJS) into plain row objects
     * keyed the same way EmployeesExport's headings map to snake_case — this endpoint
     * just receives that JSON and hands it to the same create/update logic the single-
     * record endpoints use, row by row.
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'rows' => 'required|array|min:1',
            ]);

            $summary = EmployeeRepository::importRows($request->input('rows'));

            return response()->json(['status' => 'success', 'data' => $summary], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        try {
            $employee = \App\Employee::with([
                'managementHierarchy',
                'department',
                'designation',
                'factory',
                'country',
                'reportingManager:id,employee_no,full_name,first_name,last_name',
                'team',
                'baseTeam',
            ])->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $employee], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function journey($id)
    {
        try {
            \App\Employee::findOrFail($id);
            $journey = EmployeeRepository::getJourney($id);
            return response()->json(['status' => 'success', 'data' => $journey], 200);
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
