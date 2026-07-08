<?php

namespace App\Http\Controllers\Api;

use App\Exports\ProductsExport;
use App\Http\Controllers\Controller;
use App\Http\Repositories\ProductRepository;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class ProductController extends Controller
{
    public function export()
    {
        return Excel::download(new ProductsExport(), 'Products_' . date('Y_m_d_H_i_s') . '.xlsx');
    }

    /**
     * The Excel file itself is parsed client-side (via SheetJS) into plain row objects
     * keyed the same way ProductsExport's headings map to snake_case — this endpoint
     * just receives that JSON and hands it to the same create/update logic the
     * masterDetails/CRUD flow uses, row by row.
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'rows' => 'required|array|min:1',
            ]);

            $summary = ProductRepository::importRows($request->input('rows'));

            return response()->json(['status' => 'success', 'data' => $summary], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
