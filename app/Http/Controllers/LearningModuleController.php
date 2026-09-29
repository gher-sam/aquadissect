<?php

namespace App\Http\Controllers;

use App\Models\ClassModuleAssignment;
use App\Models\LearningModuleAsset;
use App\Models\LearningModuleVersion;
use App\Models\User;
use App\Services\LearningModuleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LearningModuleController extends Controller
{
    public function index(Request $request, LearningModuleCatalog $catalog): JsonResponse
    {
        return response()->json(['learningModules' => $catalog->for($request->user())]);
    }

    public function manifest(Request $request, LearningModuleVersion $version, LearningModuleCatalog $catalog): JsonResponse
    {
        $this->authorizeVersion($request, $version);

        return response()->json(['module' => $catalog->publicManifest(
            $version->load(['module', 'assets']),
            $request->integer('classId') ?: null,
        )]);
    }

    public function asset(Request $request, LearningModuleVersion $version, LearningModuleAsset $asset): BinaryFileResponse
    {
        abort_unless($asset->learning_module_version_id === $version->id, 404);
        $this->authorizeVersion($request, $version);
        $disk = Storage::disk(config('learning-modules.disk'));
        abort_unless($disk->exists($asset->relative_path), 404, 'The learning-module asset is unavailable.');

        return response()->file($disk->path($asset->relative_path), [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => in_array($version->status, [LearningModuleVersion::STATUS_PUBLISHED, LearningModuleVersion::STATUS_ARCHIVED], true)
                ? 'private, max-age=31536000, immutable'
                : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeVersion(Request $request, LearningModuleVersion $version): void
    {
        $user = $request->user();
        if ($user->role === User::ROLE_PROGRAM_HEAD) {
            abort_unless(in_array($version->status, ['published', 'review'], true), 403);
            return;
        }
        if ($user->role !== User::ROLE_STUDENT) {
            return;
        }
        abort_unless(in_array($version->status, [LearningModuleVersion::STATUS_PUBLISHED, LearningModuleVersion::STATUS_ARCHIVED], true), 403);
        $classId = $request->integer('classId');
        abort_unless($classId > 0, 422, 'Choose the section for this learning module.');
        $assignment = ClassModuleAssignment::query()->where([
            'class_id' => $classId,
            'active' => true,
        ])->whereHas('learningClass.enrollments', fn ($query) => $query
            ->where('user_id', $user->id)->where('status', 'active'))
            ->where('learning_module_id', $version->learning_module_id)->first();
        abort_unless($assignment, 403);
        $isAssignedVersion = $assignment->learning_module_version_id === $version->id;
        $isPinnedVersion = $version->progress()->where([
            'user_id' => $user->id,
            'class_id' => $classId,
        ])->exists();
        abort_unless($isAssignedVersion || $isPinnedVersion, 403);
    }
}
