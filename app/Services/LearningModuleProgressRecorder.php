<?php

namespace App\Services;

use App\Models\ClassModuleAssignment;
use App\Models\LearningClass;
use App\Models\LearningModuleAttempt;
use App\Models\LearningModuleProgress;
use App\Models\LearningModuleVersion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LearningModuleProgressRecorder
{
    /** @param array<string, mixed> $data */
    public function record(User $user, LearningModuleVersion $version, array $data): array
    {
        abort_unless($user->role === User::ROLE_STUDENT, 403);
        abort_unless(in_array($version->status, [LearningModuleVersion::STATUS_PUBLISHED, LearningModuleVersion::STATUS_ARCHIVED], true), 403);
        $class = LearningClass::query()->whereKey($data['classId'])->where('status', 'active')
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $user->id)->where('status', 'active'))
            ->firstOrFail();
        $assignment = ClassModuleAssignment::query()->where([
            'class_id' => $class->id,
            'learning_module_id' => $version->learning_module_id,
            'active' => true,
        ])->firstOrFail();
        $isAssignedVersion = $assignment->learning_module_version_id === $version->id;
        $isPinnedVersion = LearningModuleProgress::query()->where([
            'user_id' => $user->id,
            'class_id' => $class->id,
            'learning_module_version_id' => $version->id,
        ])->exists();
        abort_unless($isAssignedVersion || $isPinnedVersion, 403, 'This learner is assigned to another module version.');

        $manifest = $version->manifest;
        $required = collect($manifest['requiredStepIds'] ?? [])->map(fn ($id) => (string) $id)->values();
        $submitted = collect($data['completedStepIds'] ?? [])->map(fn ($id) => (string) $id)->unique()->values();
        if ($submitted->diff($required)->isNotEmpty()) {
            throw ValidationException::withMessages(['completedStepIds' => 'Progress contains an unknown module step.']);
        }

        $progress = LearningModuleProgress::query()->firstOrNew([
            'user_id' => $user->id,
            'class_id' => $class->id,
            'learning_module_version_id' => $version->id,
        ]);
        $merged = collect($progress->completed_step_ids ?? [])->merge($submitted)->unique()->values();
        $attempt = null;
        $duplicate = false;
        if (isset($data['answers'])) {
            $attempt = LearningModuleAttempt::query()->where('user_id', $user->id)
                ->where('attempt_uuid', $data['clientAttemptUuid'])->first();
            if ($attempt) {
                abort_unless($attempt->learning_module_version_id === $version->id && $attempt->class_id === $class->id, 409, 'That attempt identifier is already used for another module or section.');
                $duplicate = true;
            } else {
                $questions = collect($manifest['activity']['questions'] ?? []);
                $score = $questions->filter(fn (array $question) => ($data['answers'][$question['id']] ?? null) === $question['answer'])->count();
                $attempt = LearningModuleAttempt::query()->create([
                    'user_id' => $user->id,
                    'class_id' => $class->id,
                    'learning_module_version_id' => $version->id,
                    'attempt_uuid' => $data['clientAttemptUuid'],
                    'score' => $score,
                    'total' => $questions->count(),
                    'source' => $data['source'],
                    'completed_at' => $data['completedAt'],
                    'submitted_at' => now(),
                    'synced_at' => now(),
                ]);
            }
        }

        $hasAttempt = $attempt || LearningModuleAttempt::query()->where([
            'user_id' => $user->id,
            'class_id' => $class->id,
            'learning_module_version_id' => $version->id,
        ])->exists();
        $complete = $required->diff($merged)->isEmpty() && $hasAttempt;
        $progress->fill([
            'completed_step_ids' => $merged->all(),
            'source' => $data['source'],
            'completed_at' => $complete ? ($progress->completed_at ?? now()) : null,
            'synced_at' => now(),
        ])->save();

        return [
            'progress' => [
                'completedStepIds' => $progress->completed_step_ids,
                'completedSteps' => count($progress->completed_step_ids),
                'totalSteps' => $required->count(),
                'completed' => $progress->completed_at !== null,
                'completedAt' => $progress->completed_at?->toIso8601String(),
                'activitySubmitted' => (bool) $hasAttempt,
            ],
            'attempt' => $attempt ? ['score' => $attempt->score, 'total' => $attempt->total] : null,
            'duplicate' => $duplicate,
            'assignmentId' => $assignment->id,
        ];
    }
}
