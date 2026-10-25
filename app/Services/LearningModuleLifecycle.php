<?php

namespace App\Services;

use App\Models\ClassModuleAssignment;
use App\Models\LearningClass;
use App\Models\LearningModuleOverride;
use App\Models\LearningModuleVersion;
use App\Models\LessonResult;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearningModuleLifecycle
{
    public function submit(LearningModuleVersion $version, User $actor): LearningModuleVersion
    {
        if ($version->status !== LearningModuleVersion::STATUS_DRAFT) {
            throw ValidationException::withMessages(['module' => 'Only a draft module version can be submitted for review.']);
        }
        $version->update(['status' => LearningModuleVersion::STATUS_REVIEW]);

        return $version->fresh(['module', 'assets', 'reviews']);
    }

    public function review(
        LearningModuleVersion $version,
        User $reviewer,
        string $decision,
        string $notes,
        ?string $evidenceReference,
        bool $localSopConfirmed,
    ): LearningModuleVersion {
        if ($version->status !== LearningModuleVersion::STATUS_REVIEW) {
            throw ValidationException::withMessages(['module' => 'Only a version in review can receive a faculty decision.']);
        }
        if ($version->imported_by === $reviewer->id) {
            throw ValidationException::withMessages(['reviewer' => 'The content uploader cannot approve the same module version.']);
        }
        if (! in_array($decision, ['approved', 'changes_requested'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose approved or changes requested.']);
        }
        if ($decision === 'approved' && (! $localSopConfirmed || ! $evidenceReference)) {
            throw ValidationException::withMessages([
                'evidenceReference' => 'Approval requires a reference to the confirmed institutional or faculty-approved laboratory procedure.',
            ]);
        }

        return DB::transaction(function () use ($version, $reviewer, $decision, $notes, $evidenceReference): LearningModuleVersion {
            if ($decision === 'approved') {
                $manifest = $version->manifest;
                $manifest['tools'] = array_map(function (array $tool): array {
                    $tool['validationStatus'] = 'faculty_validated';

                    return $tool;
                }, $manifest['tools'] ?? []);
                foreach ($manifest['references'] as &$reference) {
                    if (($reference['id'] ?? null) === 'local-sop-pending') {
                        $reference['title'] = 'Faculty-confirmed MB 304L laboratory procedure';
                        $reference['publisher'] = 'Faculty Reviewer';
                        $reference['scope'] = 'Confirmation recorded in the module review evidence; distribution rights remain governed by the institution.';
                    }
                }
                unset($reference);
                $manifest['statusNotice'] = 'Faculty-reviewed learning content. Follow the current instructor direction and institution-approved laboratory procedure.';
                $version->update(['manifest' => $manifest]);
                foreach ($version->assets as $asset) {
                    $metadata = $asset->metadata ?? [];
                    $metadata['reviewStatus'] = 'faculty_validated';
                    $asset->update(['metadata' => $metadata]);
                }
            }

            $version->reviews()->create([
                'reviewer_id' => $reviewer->id,
                'reviewer_role' => 'Faculty Reviewer',
                'decision' => $decision,
                'notes' => $notes,
                'evidence_reference' => $evidenceReference,
            ]);
            if ($decision === 'changes_requested') {
                $version->update(['status' => LearningModuleVersion::STATUS_DRAFT]);
            }

            return $version->fresh(['module', 'assets', 'reviews']);
        });
    }

    /** @return array{version: LearningModuleVersion, assignments: int, pinned: int, legacyOverrides: int} */
    public function publish(LearningModuleVersion $version, User $actor, array $classIds = []): array
    {
        if ($version->status !== LearningModuleVersion::STATUS_REVIEW) {
            throw ValidationException::withMessages(['module' => 'Only a reviewed module version can be published.']);
        }
        $review = $version->reviews()->latest()->first();
        $latestContentChange = collect([
            $version->updated_at,
            $version->assets()->max('updated_at'),
        ])->filter()->map(fn ($value) => $value instanceof Carbon ? $value : Carbon::parse($value))->max();
        if (! $review || $review->decision !== 'approved' || $review->reviewer_id === $version->imported_by
            || $review->created_at->lessThan($latestContentChange)) {
            throw ValidationException::withMessages(['review' => 'An independent approved faculty review is required before publication.']);
        }
        if (collect($version->manifest['tools'] ?? [])->contains(fn (array $tool) => ($tool['validationStatus'] ?? null) !== 'faculty_validated')) {
            throw ValidationException::withMessages(['content' => 'Every laboratory tool must be faculty validated before publication.']);
        }
        $requiredToolFields = ['name', 'purpose', 'handling', 'applicableStep', 'safety', 'sourceIds', 'licence', 'validationStatus'];
        if (collect($version->manifest['tools'] ?? [])->contains(fn (array $tool) => collect($requiredToolFields)
            ->contains(fn (string $field) => ! array_key_exists($field, $tool) || $tool[$field] === '' || $tool[$field] === []))) {
            throw ValidationException::withMessages(['content' => 'Every laboratory tool requires its name, purpose, handling, procedure step, precaution, source, licence, and validation status.']);
        }
        if ($version->assets->contains(fn ($asset) => ! in_array($asset->mime_type, ['image/webp', 'video/mp4'], true)
            || ! ($asset->metadata['source'] ?? null)
            || ! ($asset->metadata['licence'] ?? null)
            || ! ($asset->metadata['caption'] ?? null)
            || ($asset->metadata['reviewStatus'] ?? null) !== 'faculty_validated')) {
            throw ValidationException::withMessages(['assets' => 'Every uploaded asset requires a supported type, provenance, caption, licence, checksum, and faculty validation.']);
        }

        return DB::transaction(function () use ($version, $actor, $classIds): array {
            $classes = LearningClass::query()->whereIn('id', $classIds)->where('status', 'active')
                ->whereHas('course', fn ($query) => $query->where('code', 'MB-304L'))->lockForUpdate()->get();
            if ($classes->count() !== count($classIds)) {
                throw ValidationException::withMessages(['classIds' => 'Choose distinct active MB 304L sections. Refresh the section list if it has changed.']);
            }
            LearningModuleVersion::query()->where('learning_module_id', $version->learning_module_id)
                ->where('id', '<>', $version->id)->where('is_current', true)->update(['is_current' => false]);
            $version->update([
                'status' => LearningModuleVersion::STATUS_PUBLISHED,
                'is_current' => true,
                'published_at' => now(),
                'archived_at' => null,
            ]);

            $assigned = 0;
            $pinned = 0;
            $legacyOverrides = 0;
            foreach ($classes as $class) {
                $assignment = ClassModuleAssignment::query()->where([
                    'class_id' => $class->id,
                    'learning_module_id' => $version->learning_module_id,
                ])->first();
                if ($assignment && $assignment->learning_module_version_id !== $version->id) {
                    $pinned += $assignment->version?->progress()
                        ->where('class_id', $class->id)->distinct('user_id')->count('user_id') ?? 0;
                }
                $assignment = ClassModuleAssignment::query()->updateOrCreate(
                    ['class_id' => $class->id, 'learning_module_id' => $version->learning_module_id],
                    [
                        'learning_module_version_id' => $version->id,
                        'assigned_by' => $actor->id,
                        'required' => true,
                        'active' => true,
                        'assigned_at' => now(),
                    ],
                );
                $assigned++;

                $legacyStudents = LessonResult::query()->where('class_id', $class->id)->distinct()->pluck('user_id');
                foreach ($legacyStudents as $studentId) {
                    $exists = LearningModuleOverride::query()->where([
                        'class_id' => $class->id,
                        'user_id' => $studentId,
                        'learning_module_id' => $version->learning_module_id,
                    ])->whereNull('revoked_at')->exists();
                    if (! $exists) {
                        LearningModuleOverride::query()->create([
                            'class_id' => $class->id,
                            'user_id' => $studentId,
                            'learning_module_id' => $version->learning_module_id,
                            'reason' => 'Legacy access retained because an anatomy attempt was recorded before the prerequisite was published.',
                            'granted_by' => $actor->id,
                            'granted_at' => now(),
                        ]);
                        $legacyOverrides++;
                    }
                }
            }

            return ['version' => $version->fresh(), 'assignments' => $assigned, 'pinned' => $pinned, 'legacyOverrides' => $legacyOverrides];
        });
    }

    public function archive(LearningModuleVersion $version): LearningModuleVersion
    {
        if ($version->status !== LearningModuleVersion::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['module' => 'Only a published version can be archived.']);
        }
        $version->update(['status' => LearningModuleVersion::STATUS_ARCHIVED, 'is_current' => false, 'archived_at' => now()]);

        return $version->fresh();
    }
}
