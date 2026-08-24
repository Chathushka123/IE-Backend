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
    /** Filter keys accepted by both export() and filter() — the Export/Filter dialog's payload shape. */
    private const FILTER_KEYS = [
        'customer_id',
        'season_id',
        'factory_id',
        'created_at_from',
        'created_at_to',
        'customer_requested_delivery_date_from',
        'customer_requested_delivery_date_to',
    ];

    public function export(Request $request)
    {
        $filters = $request->only(self::FILTER_KEYS);

        return Excel::download(new ProductsExport($filters), 'Products_' . date('Y_m_d_H_i_s') . '.xlsx');
    }

    /**
     * Backend-paginated, filtered product list for the Products table's "Filter"
     * button — same filter semantics as export() (see ProductRepository::applyExportFilters()),
     * just returned as JSON instead of an Excel file.
     */
    public function filter(Request $request)
    {
        $filters = $request->only(self::FILTER_KEYS);
        $page = max((int) $request->input('page', 1), 1);
        $perPage = max((int) $request->input('per_page', 20), 1);

        $query = Product::with(['productCategory', 'customer', 'season', 'factories']);

        ProductRepository::applyExportFilters($query, $filters);

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $paginated->items(),
            'meta' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
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
