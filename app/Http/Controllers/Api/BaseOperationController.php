<?php

namespace App\Http\Controllers\Api;

use App\Exports\BaseOperationsExport;
use App\Http\Controllers\Controller;
use App\BaseOperation;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class BaseOperationController extends Controller
{
    public function export()
    {
        return Excel::download(new BaseOperationsExport(), 'BaseOperations_' . date('Y_m_d_H_i_s') . '.xlsx');
    }

    public function getByCategories(Request $request)
    {
        $request->validate([
            'category_ids'   => 'required|array|min:1',
            'category_ids.*' => 'integer|min:1',
        ]);

        $operations = BaseOperation::with('category')
            ->whereIn('base_operation_category_id', $request->category_ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $operations,
        ]);
    }
}
