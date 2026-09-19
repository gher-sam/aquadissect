<?php

namespace App\Http\Controllers;

use App\Models\LearningModuleVersion;
use App\Services\DelayedCloneWrite;
use App\Services\LearningModuleProgressRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LearningModuleProgressController extends Controller
{
    public function store(
        Request $request,
        LearningModuleVersion $version,
        LearningModuleProgressRecorder $recorder,
        DelayedCloneWrite $writes,
    ): JsonResponse {
        $data = $request->validate([
            'classId' => ['required', 'integer', 'exists:classes,id'],
            'completedStepIds' => ['required', 'array'],
            'completedStepIds.*' => ['string', 'max:100'],
            'clientAttemptUuid' => ['nullable', 'required_with:answers', 'uuid'],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string', 'max:500'],
            'source' => ['required', Rule::in(['online', 'offline'])],
            'completedAt' => ['required', 'date'],
        ]);
        $payload = $writes->transaction(fn () => $recorder->record($request->user(), $version, $data));

        return response()->json($payload, $payload['duplicate'] ? 200 : 201);
    }
}
