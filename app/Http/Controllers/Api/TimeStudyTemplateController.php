<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\TimeStudyTemplateRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class TimeStudyTemplateController extends Controller
{
    public function index(Request $request)
    {
        return $this->respond(fn () => TimeStudyTemplateRepository::search($request->query()));
    }

    public function show($id)
    {
        return $this->respond(fn () => TimeStudyTemplateRepository::find((int) $id));
    }

    public function store(Request $request)
    {
        return $this->respond(fn () => DB::transaction(fn () => TimeStudyTemplateRepository::create($request->all())));
    }

    public function saveDraft(Request $request, $id)
    {
        return $this->respond(fn () => TimeStudyTemplateRepository::saveDraft((int) $id, $request->all()));
    }

    public function discardDraft($id)
    {
        return $this->respond(fn () => TimeStudyTemplateRepository::discardDraft((int) $id));
    }

    public function publish($id)
    {
        return $this->respond(fn () => TimeStudyTemplateRepository::publish((int) $id));
    }

    public function duplicate($id)
    {
        return $this->respond(fn () => TimeStudyTemplateRepository::cloneTemplate((int) $id));
    }

    public function archive($id)
    {
        return $this->respond(function () use ($id) {
            TimeStudyTemplateRepository::archive((int) $id);

            return null;
        });
    }

    public function preview(Request $request)
    {
        return $this->respond(fn () => TimeStudyTemplateRepository::preview($request->all()));
    }

    public function resolve(Request $request)
    {
        return $this->respond(function () use ($request) {
            $request->validate([
                'operation_id' => 'required|integer',
                'purpose' => 'nullable|in:perf,smv',
            ]);

            return TimeStudyTemplateRepository::resolve($request->integer('operation_id'), $request->input('purpose', 'perf'));
        });
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
