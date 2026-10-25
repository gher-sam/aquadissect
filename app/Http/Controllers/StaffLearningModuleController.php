<?php

namespace App\Http\Controllers;

use App\Models\LearningModuleVersion;
use App\Services\LearningModuleCatalog;
use App\Services\LearningModuleLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffLearningModuleController extends Controller
{
    public function submit(Request $request, LearningModuleVersion $version, LearningModuleLifecycle $lifecycle, LearningModuleCatalog $catalog): JsonResponse
    {
        $updated = $lifecycle->submit($version, $request->user());

        return response()->json(['module' => $catalog->staffPayload($updated)]);
    }

    public function review(Request $request, LearningModuleVersion $version, LearningModuleLifecycle $lifecycle, LearningModuleCatalog $catalog): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'changes_requested'])],
            'notes' => ['required', 'string', 'min:10', 'max:3000'],
            'evidenceReference' => ['nullable', 'string', 'max:500'],
            'localSopConfirmed' => ['required', 'boolean'],
        ]);
        $updated = $lifecycle->review(
            $version,
            $request->user(),
            $data['decision'],
            $data['notes'],
            $data['evidenceReference'] ?? null,
            $data['localSopConfirmed'],
        );

        return response()->json(['module' => $catalog->staffPayload($updated)]);
    }

    public function publish(Request $request, LearningModuleVersion $version, LearningModuleLifecycle $lifecycle, LearningModuleCatalog $catalog): JsonResponse
    {
        $data = $request->validate([
            'classIds' => ['present', 'array', 'max:1000'],
            'classIds.*' => ['integer', 'distinct'],
        ]);
        $result = $lifecycle->publish($version, $request->user(), $data['classIds']);

        return response()->json([
            'module' => $catalog->staffPayload($result['version']->load(['module', 'assets', 'reviews'])),
            'assignments' => $result['assignments'],
            'pinned' => $result['pinned'],
            'legacyOverrides' => $result['legacyOverrides'],
        ]);
    }

    public function archive(Request $request, LearningModuleVersion $version, LearningModuleLifecycle $lifecycle, LearningModuleCatalog $catalog): JsonResponse
    {
        return response()->json(['module' => $catalog->staffPayload(
            $lifecycle->archive($version)->load(['module', 'assets', 'reviews']),
        )]);
    }
}
