<?php

namespace App\Services;

use App\Models\ClassModuleAssignment;
use App\Models\LearningModuleProgress;
use App\Models\LearningModuleVersion;
use App\Models\User;
use Illuminate\Support\Carbon;

class LearningModuleCatalog
{
    public function __construct(private readonly LearningModuleGate $gate) {}

    /** @return array<int, array<string, mixed>> */
    public function for(User $user): array
    {
        if ($user->role === User::ROLE_STUDENT) {
            return ClassModuleAssignment::query()
                ->with(['module', 'version.assets'])
                ->where('active', true)
                ->whereHas('version', fn ($query) => $query->whereIn('status', [
                    LearningModuleVersion::STATUS_PUBLISHED,
                    LearningModuleVersion::STATUS_ARCHIVED,
                ]))
                ->whereHas('learningClass', fn ($query) => $query->where('status', 'active')
                    ->whereHas('enrollments', fn ($enrollments) => $enrollments
                        ->where('user_id', $user->id)->where('status', 'active')))
                ->orderBy('class_id')
                ->get()
                ->map(fn (ClassModuleAssignment $assignment) => $this->studentPayload($assignment, $user))
                ->values()->all();
        }

        return LearningModuleVersion::query()->with(['module', 'assets', 'reviews'])
            ->orderByRaw("CASE status WHEN 'published' THEN 1 WHEN 'review' THEN 2 WHEN 'draft' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at')
            ->get()->map(fn (LearningModuleVersion $version) => $this->staffPayload($version))->all();
    }

    /** @return array<string, mixed> */
    public function studentPayload(ClassModuleAssignment $assignment, User $user): array
    {
        $progress = LearningModuleProgress::query()->with('version.assets')->where([
            'user_id' => $user->id,
            'class_id' => $assignment->class_id,
        ])->whereHas('version', fn ($query) => $query
            ->where('learning_module_id', $assignment->learning_module_id))
            ->latest('updated_at')->first();
        $version = $progress?->version ?? $assignment->version;
        $manifest = $version->manifest;
        $required = $manifest['requiredStepIds'] ?? [];

        return [
            'id' => $version->id,
            'moduleId' => $assignment->learning_module_id,
            'assignmentId' => $assignment->id,
            'classId' => $assignment->class_id,
            'slug' => $assignment->module->slug,
            'title' => $assignment->module->title,
            'description' => $assignment->module->description,
            'version' => $version->version,
            'status' => $version->status,
            'required' => $assignment->required,
            'dueAt' => $assignment->due_at?->toIso8601String(),
            'progress' => [
                'completedStepIds' => $progress?->completed_step_ids ?? [],
                'completedSteps' => count($progress?->completed_step_ids ?? []),
                'totalSteps' => count($required),
                'completed' => $progress?->completed_at !== null,
                'completedAt' => $progress?->completed_at?->toIso8601String(),
                'overrideActive' => $this->gate->hasOverride($user, $assignment),
            ],
            'manifestUrl' => url("/api/learning-modules/{$version->id}/manifest?classId={$assignment->class_id}"),
            'pinned' => $version->id !== $assignment->learning_module_version_id,
        ];
    }

    /** @return array<string, mixed> */
    public function staffPayload(LearningModuleVersion $version): array
    {
        $latestReview = $version->reviews->sortByDesc('created_at')->first();
        $latestContentChange = collect([
            $version->updated_at,
            $version->assets->max('updated_at'),
        ])->filter()->map(fn ($value) => $value instanceof Carbon ? $value : Carbon::parse($value))->max();
        $approvalCurrent = $latestReview?->decision === 'approved'
            && $latestReview->created_at?->greaterThanOrEqualTo($latestContentChange);

        return [
            'id' => $version->id,
            'moduleId' => $version->learning_module_id,
            'slug' => $version->module->slug,
            'title' => $version->module->title,
            'description' => $version->module->description,
            'version' => $version->version,
            'status' => $version->status,
            'isCurrent' => $version->is_current,
            'toolCount' => count($version->manifest['tools'] ?? []),
            'assetCount' => $version->assets->count(),
            'reviewCount' => $version->reviews->count(),
            'latestReview' => $latestReview ? [
                'decision' => $latestReview->decision,
                'reviewerRole' => $latestReview->reviewer_role,
                'notes' => $latestReview->notes,
                'evidenceReference' => $latestReview->evidence_reference,
                'createdAt' => $latestReview->created_at?->toIso8601String(),
            ] : null,
            'approvalCurrent' => $approvalCurrent,
            'importedBy' => $version->imported_by,
            'tools' => collect($version->manifest['tools'] ?? [])->map(fn (array $tool) => [
                'key' => $tool['key'],
                'name' => $tool['name'],
                'category' => $tool['category'],
                'validationStatus' => $tool['validationStatus'],
            ])->values()->all(),
            'manifestUrl' => url("/api/learning-modules/{$version->id}/manifest"),
        ];
    }

    /** @return array<string, mixed> */
    public function publicManifest(LearningModuleVersion $version, ?int $classId = null): array
    {
        $manifest = $version->manifest;
        $manifest['id'] = $version->id;
        $manifest['status'] = $version->status;
        $manifest['isCurrent'] = $version->is_current;
        $manifest['activity']['questions'] = array_map(function (array $question): array {
            unset($question['answer']);

            return $question;
        }, $manifest['activity']['questions'] ?? []);
        $manifest['assetRecords'] = $version->assets->mapWithKeys(fn ($asset) => [
            $asset->asset_key => [
                'id' => $asset->id,
                'url' => url("/api/learning-module-versions/{$version->id}/assets/{$asset->id}")
                    .($classId ? '?classId='.$classId : ''),
                'mimeType' => $asset->mime_type,
                'sizeBytes' => $asset->size_bytes,
                'sha256' => $asset->sha256,
                'metadata' => $asset->metadata,
            ],
        ])->all();

        return $manifest;
    }
}
