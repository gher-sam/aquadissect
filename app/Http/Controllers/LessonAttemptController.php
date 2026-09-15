<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLessonAttemptRequest;
use App\Models\LessonResult;
use App\Models\SpeciesPackage;
use App\Services\DelayedCloneWrite;
use App\Services\SpeciesPackageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LessonAttemptController extends Controller
{
    public function store(
        string $lessonKey,
        StoreLessonAttemptRequest $request,
        SpeciesPackageContent $content,
        DelayedCloneWrite $writes,
    ): JsonResponse {
        $validated = $request->validated();
        $existing = LessonResult::query()
            ->where('user_id', $request->user()->id)
            ->where('attempt_uuid', $validated['clientAttemptUuid'])
            ->first();
        if ($existing) {
            abort_if($existing->species_package_id !== (int) $validated['packageId'], 409, 'That attempt identifier is already used for another package.');

            return response()->json(['result' => $existing->portalPayload(), 'duplicate' => true]);
        }

        $package = SpeciesPackage::query()->with('assets')->findOrFail($validated['packageId']);
        $content->authorize($request->user(), $package);
        $manifest = $content->manifest($package);
        if (($manifest['lessonKey'] ?? null) !== $lessonKey || $package->version !== $validated['packageVersion']) {
            throw ValidationException::withMessages(['packageVersion' => 'The lesson key or package version does not match the selected package.']);
        }

        $quiz = $manifest['contentData']['quiz'];
        if (($quiz['formative'] ?? false) !== true && $validated['source'] === 'offline') {
            throw ValidationException::withMessages(['source' => 'High-stakes assessments require an online session.']);
        }

        $questions = collect($quiz['questions']);
        $answers = $validated['answers'];
        $score = $questions->filter(fn (array $question) => ($answers[$question['id']] ?? null) === $question['answer'])->count();

        return $writes->transaction(function () use ($request, $package, $lessonKey, $validated, $score, $questions): JsonResponse {
            $result = $request->user()->lessonResults()->create([
                'species_package_id' => $package->id,
                'lesson_key' => $lessonKey,
                'attempt_uuid' => $validated['clientAttemptUuid'],
                'package_version' => $package->version,
                'score' => $score,
                'total' => $questions->count(),
                'source' => $validated['source'],
                'completed_at' => $validated['completedAt'],
                'submitted_at' => now(),
                'synced_at' => now(),
            ]);

            return response()->json(['result' => $result->portalPayload(), 'duplicate' => false], 201);
        });
    }
}
