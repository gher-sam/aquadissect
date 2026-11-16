<?php

namespace App\Http\Controllers;

use App\Models\PracticeCompletion;
use App\Models\SpeciesPackage;
use App\Models\User;
use App\Services\AttemptOffering;
use App\Services\DelayedCloneWrite;
use App\Services\PracticeChallenges;
use App\Services\SpeciesPackageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PracticeController extends Controller
{
    public function index(Request $request, SpeciesPackage $package, SpeciesPackageContent $content, AttemptOffering $offering, PracticeChallenges $practice): JsonResponse
    {
        $data = $request->validate(['classId' => ['nullable', 'integer', 'exists:classes,id']]);
        $classId = $this->resolveClass($request, $package, $data, $content, $offering);
        $definitions = $practice->definitions($package);

        return response()->json([
            'packageId' => $package->id,
            'packageVersion' => $package->version,
            'classId' => $classId,
            'preview' => $request->user()->role !== User::ROLE_STUDENT,
            'challenges' => array_map(function (array $challenge): array {
                unset($challenge['answer'], $challenge['explanation']);

                return $challenge;
            }, $definitions),
            'summary' => $practice->summary($definitions, $package, $request->user(), $classId),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function answer(Request $request, SpeciesPackage $package, SpeciesPackageContent $content, AttemptOffering $offering, PracticeChallenges $practice, DelayedCloneWrite $writes): JsonResponse
    {
        $data = $request->validate([
            'classId' => ['nullable', 'integer', 'exists:classes,id'],
            'packageVersion' => ['required', Rule::in([$package->version])],
            'challengeId' => ['required', 'string', 'max:120'],
            'answer' => ['required', 'string', 'max:500'],
            'xp' => ['prohibited'],
            'score' => ['prohibited'],
        ]);
        $classId = $this->resolveClass($request, $package, $data, $content, $offering);
        $definitions = $practice->definitions($package);
        $challenge = collect($definitions)->firstWhere('id', $data['challengeId']);
        abort_unless($challenge && in_array($data['answer'], $challenge['options'], true), 422, 'Choose an answer from the current challenge.');

        return $writes->transaction(function () use ($request, $package, $practice, $definitions, $challenge, $data, $classId): JsonResponse {
            $correct = $data['answer'] === $challenge['answer'];
            $preview = $request->user()->role !== User::ROLE_STUDENT;
            $awarded = 0;
            if ($correct && ! $preview) {
                $reward = PracticeCompletion::query()->firstOrCreate([
                    'user_id' => $request->user()->id,
                    'class_id' => $classId,
                    'species_package_id' => $package->id,
                    'challenge_key' => $challenge['id'],
                ], ['points' => PracticeChallenges::POINTS, 'completed_at' => now()]);
                $awarded = $reward->wasRecentlyCreated ? $reward->points : 0;
            }

            return response()->json([
                'correct' => $correct,
                'awardedXp' => $awarded,
                'preview' => $preview,
                'feedback' => $correct ? $challenge['explanation'] : 'Not quite. Review the hint or revisit the lesson, then try again. There is no point penalty.',
                'summary' => $practice->summary($definitions, $package, $request->user(), $classId),
            ])->header('Cache-Control', 'private, no-store');
        });
    }

    private function resolveClass(Request $request, SpeciesPackage $package, array $data, SpeciesPackageContent $content, AttemptOffering $offering): ?int
    {
        $classId = isset($data['classId']) ? (int) $data['classId'] : null;
        $content->authorize($request->user(), $package, $classId);

        return $offering->resolve($request->user(), $package->id, $classId);
    }
}
