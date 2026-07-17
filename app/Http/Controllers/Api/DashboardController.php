<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\DashboardRepository;
use Exception;

class DashboardController extends Controller
{
    public function overview()
    {
        try {
            $data = DashboardRepository::getOverview();
            return response()->json(['status' => 'success', 'data' => $data], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
