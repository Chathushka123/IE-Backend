<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\ProductMilestoneRepository;
use App\Exports\ProductMilestonesExport;
use App\ProductMilestone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class ProductMilestoneController extends Controller
{
    private const FILTER_KEYS = [
        'customer_id', 'season_id', 'product_category_id', 'factory_id',
        'planned_cut_date_from', 'planned_cut_date_to',
        'planned_production_start_date_from', 'planned_production_start_date_to',
        'planned_production_end_date_from', 'planned_production_end_date_to',
        'planned_etd_from', 'planned_etd_to',
        'planned_eta_from', 'planned_eta_to',
        'created_at_from', 'created_at_to',
    ];

    public function getByProduct($productId)
    {
        try {
            $record = ProductMilestone::where('product_id', $productId)->first();

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $record = ProductMilestoneRepository::createRec($request->all());
            DB::commit();

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $record = ProductMilestoneRepository::updateRec($id, $request->all());
            DB::commit();

            return response()->json(['status' => 'success', 'data' => $record], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Backend-paginated, filtered milestone list for the Milestones table's "Filter"
     * button — same filter semantics as export() (see ProductMilestoneRepository::applyExportFilters()).
     */
    public function filter(Request $request)
    {
        $filters = $request->only(self::FILTER_KEYS);
        $page = max((int) $request->input('page', 1), 1);
        $perPage = max((int) $request->input('per_page', 20), 1);

        $query = ProductMilestone::with(['product']);

        ProductMilestoneRepository::applyExportFilters($query, $filters);

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

    public function export(Request $request)
    {
        $filters = $request->only(self::FILTER_KEYS);

        return Excel::download(new ProductMilestonesExport($filters), 'ProductMilestones_' . date('Y_m_d_H_i_s') . '.xlsx');
    }

    public function import(Request $request)
    {
        try {
            $request->validate([
                'rows' => 'required|array|min:1',
            ]);

            $summary = ProductMilestoneRepository::importRows($request->input('rows'));

            return response()->json(['status' => 'success', 'data' => $summary], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
