<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Operation;
use Illuminate\Http\Request;

class OperationController extends Controller
{
    public function getByCategories(Request $request)
    {
        $request->validate([
            'category_ids'   => 'required|array|min:1',
            'category_ids.*' => 'integer|min:1',
        ]);

        $operations = Operation::with('category', 'skills')
            ->whereIn('operation_category_id', $request->category_ids)
            ->where('is_active', true)
            ->orderBy('description')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $operations,
        ]);
    }
}
