<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\TimeStudyPlanReportRepository;
use App\Http\Repositories\TimeStudyPlanRepository;
use Exception;
use Illuminate\Http\Request;

class TimeStudyPlanController extends Controller
{
    public function index(Request $request)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::search($request->query()));
    }

    /** How plans went: progress, whether each operation got the operators its template asks for, and who recorded what. */
    public function report(Request $request)
    {
        return $this->respond(fn () => TimeStudyPlanReportRepository::report($request->query()));
    }

    public function show($id)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::find((int) $id));
    }

    public function store(Request $request)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::create($request->all()));
    }

    public function update(Request $request, $id)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::update((int) $id, $request->all()));
    }

    public function activate($id)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::activate((int) $id));
    }

    public function complete($id)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::complete((int) $id));
    }

    public function cancel($id)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::cancel((int) $id));
    }

    public function addPairs(Request $request, $id)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::addPairs((int) $id, (array) $request->input('pairs', [])));
    }

    public function removePair($id, $itemId)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::removePair((int) $id, (int) $itemId));
    }

    public function skipPair(Request $request, $id, $itemId)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::skipPair((int) $id, (int) $itemId, $request->input('reason')));
    }

    public function restorePair($id, $itemId)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::restorePair((int) $id, (int) $itemId));
    }

    public function claim($id, $itemId)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::claim((int) $id, (int) $itemId));
    }

    public function release($id, $itemId)
    {
        return $this->respond(fn () => TimeStudyPlanRepository::release((int) $id, (int) $itemId));
    }

    private function respond(callable $action)
    {
        try {
            return response()->json(['status' => 'success', 'data' => $action()], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
