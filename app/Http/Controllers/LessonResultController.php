<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLessonResultRequest;
use App\Models\SpeciesPackage;
use App\Services\DelayedCloneWrite;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class LessonResultController extends Controller
{
    public function store(StoreLessonResultRequest $request, DelayedCloneWrite $writes): JsonResponse
    {
        $validated = $request->validated();
        $package = SpeciesPackage::query()
            ->whereHas('species', fn ($query) => $query->where('slug', 'tilapia'))
            ->where('version', '1.0.0')
            ->firstOrFail();

        return $writes->transaction(function () use ($request, $validated, $package): JsonResponse {
            $result = $request->user()->lessonResults()->create([
                'species_package_id' => $package->id,
                'lesson_key' => 'tilapia-guided-dissection',
                'attempt_uuid' => (string) Str::uuid(),
                'package_version' => '1.0.0',
                'score' => $validated['score'],
                'total' => $validated['total'],
                'source' => 'legacy',
                'completed_at' => now(),
                'submitted_at' => now(),
                'synced_at' => now(),
            ]);

            return response()->json(['result' => $result->portalPayload()], 201);
        });
    }
}
