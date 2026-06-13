<?php

namespace App\Http\Controllers\Api;

use App\Exports\EmployeesExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    public function export()
    {
        return Excel::download(new EmployeesExport(), 'Employees_' . date('Y_m_d_H_i_s') . '.xlsx');
    }
}
