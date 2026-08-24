<?php

namespace App\Http\Controllers\Api;

use App\Exports\ProductsExport;
use App\Http\Controllers\Controller;
use App\Http\Repositories\ProductRepository;
use App\Product;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class ProductController extends Controller
{
    public function export(Request $request)
    {
        $filters = $request->only([
            'customer_id',
            'season_id',
            'factory_id',
            'created_at_from',
            'created_at_to',
            'customer_requested_delivery_date_from',
            'customer_requested_delivery_date_to',
        ]);

        return Excel::download(new ProductsExport($filters), 'Products_' . date('Y_m_d_H_i_s') . '.xlsx');
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

    /**
     * Products <-> Factories is many-to-many (factory_product pivot), so it can't be
     * resolved by novelSearch's generic relation-to-FK-column guess the way Customer/
     * Season/Product Category can — this dedicated endpoint is the Factory equivalent
     * of BaseOperationController::getByCategories().
     */
    public function getByFactories(Request $request)
    {
        $request->validate([
            'factory_ids'   => 'required|array|min:1',
            'factory_ids.*' => 'integer|min:1',
        ]);

        $products = Product::with(['productCategory', 'customer', 'season', 'factories'])
            ->whereHas('factories', function ($q) use ($request) {
                $q->whereIn('factories.id', $request->factory_ids);
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $products,
        ]);
    }
}
