<?php

namespace App\Services;

use App\Models\LessonResult;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use App\Models\SpeciesPackageReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpeciesPackageLifecycle
{
    public function submit(SpeciesPackage $package, User $actor): SpeciesPackage
    {
        if ($package->status !== SpeciesPackage::STATUS_DRAFT) {
            throw ValidationException::withMessages(['package' => 'Only a draft package can be submitted for review.']);
        }

        if (! $actor->isAdministrator() && $package->imported_by !== $actor->id) {
            abort(403);
        }

        $package->update([
            'status' => SpeciesPackage::STATUS_REVIEW,
            'review_round' => ((int) $package->review_round) + 1,
            'reviewed_by' => null,
        ]);
        $package->events()->create(['user_id' => $actor->id, 'event' => 'submitted_for_review']);

        return $package->fresh(['species', 'assets', 'reviews']);
    }

    public function review(
        SpeciesPackage $package,
        User $reviewer,
        string $decision,
        string $notes,
        ?string $evidenceReference,
        bool $curriculumAlignmentConfirmed,
        bool $safetyReviewConfirmed,
    ): SpeciesPackage {
        if ($package->status !== SpeciesPackage::STATUS_REVIEW) {
            throw ValidationException::withMessages(['package' => 'Only a package in review can receive a faculty decision.']);
        }
        if ($package->imported_by === $reviewer->id) {
            throw ValidationException::withMessages(['reviewer' => 'The content uploader cannot approve the same species package.']);
        }
        if (! in_array($decision, ['approved', 'changes_requested'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose approved or changes requested.']);
        }
        if ($decision === 'approved' && (! $curriculumAlignmentConfirmed || ! $safetyReviewConfirmed || ! $evidenceReference)) {
            throw ValidationException::withMessages([
                'review' => 'Approval requires curriculum, safety, and evidence confirmations.',
            ]);
        }

        return DB::transaction(function () use (
            $package,
            $reviewer,
            $decision,
            $notes,
            $evidenceReference,
            $curriculumAlignmentConfirmed,
            $safetyReviewConfirmed,
        ): SpeciesPackage {
            $review = $package->reviews()->create([
                'reviewer_id' => $reviewer->id,
                'reviewer_role' => 'Faculty Reviewer',
                'review_round' => (int) $package->review_round,
                'content_fingerprint' => $this->contentFingerprint($package),
                'decision' => $decision,
                'notes' => $notes,
                'evidence_reference' => $evidenceReference,
                'curriculum_alignment_confirmed' => $curriculumAlignmentConfirmed,
                'safety_review_confirmed' => $safetyReviewConfirmed,
            ]);

            if ($decision === 'changes_requested') {
                $package->update(['status' => SpeciesPackage::STATUS_DRAFT, 'reviewed_by' => null]);
            }

            $package->events()->create([
                'user_id' => $reviewer->id,
                'event' => 'faculty_review_recorded',
                'metadata' => ['reviewId' => $review->id, 'decision' => $decision],
            ]);

            return $package->fresh(['species', 'assets', 'reviews']);
        });
    }

    /** @return array{package: SpeciesPackage, upgradedAssignments: int, pinnedAssignments: int} */
    public function publish(SpeciesPackage $package, User $actor): array
    {
        if ($package->status !== SpeciesPackage::STATUS_REVIEW) {
            throw ValidationException::withMessages(['package' => 'Only a reviewed package can be published.']);
        }

        $review = $package->reviews()->latest('id')->first();
        if (! $this->approvalIsCurrent($package, $review)) {
            throw ValidationException::withMessages(['review' => 'A current independent faculty approval is required before publication.']);
        }

        return DB::transaction(function () use ($package, $actor, $review): array {
            SpeciesPackage::query()
                ->where('species_id', $package->species_id)
                ->whereKeyNot($package->id)
                ->where('is_current', true)
                ->get()
                ->each->update(['is_current' => false]);

            $package->update([
                'status' => SpeciesPackage::STATUS_PUBLISHED,
                'is_current' => true,
                'reviewed_by' => $review->reviewer_id,
                'published_at' => now(),
                'archived_at' => null,
            ]);

            $upgraded = 0;
            $pinned = 0;
            SpeciesAssignment::query()
                ->where('species_id', $package->species_id)
                ->where('species_package_id', '<>', $package->id)
                ->where('active', true)
                ->with('learningClass.enrollments')
                ->each(function (SpeciesAssignment $assignment) use ($package, &$upgraded, &$pinned): void {
                    $studentIds = $assignment->learningClass->enrollments
                        ->where('status', 'active')
                        ->pluck('user_id');
                    $started = LessonResult::query()
                        ->where('species_package_id', $assignment->species_package_id)
                        ->where(fn ($query) => $query
                            ->where('class_id', $assignment->class_id)
                            ->orWhere(fn ($legacy) => $legacy->whereNull('class_id')->whereIn('user_id', $studentIds)))
                        ->exists();

                    if ($started) {
                        $pinned++;

                        return;
                    }

                    $assignment->update(['species_package_id' => $package->id]);
                    $upgraded++;
                });

            $package->events()->create([
                'user_id' => $actor->id,
                'event' => 'published',
                'metadata' => ['autoUpgradedAssignments' => $upgraded, 'pinnedAssignments' => $pinned],
            ]);

            return ['package' => $package->fresh(['species', 'assets', 'reviews']), 'upgradedAssignments' => $upgraded, 'pinnedAssignments' => $pinned];
        });
    }

    public function archive(SpeciesPackage $package, User $actor): SpeciesPackage
    {
        if ($package->status !== SpeciesPackage::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['package' => 'Only a published package can be archived.']);
        }

        $package->update(['status' => SpeciesPackage::STATUS_ARCHIVED, 'is_current' => false, 'archived_at' => now()]);
        $package->events()->create(['user_id' => $actor->id, 'event' => 'archived']);

        return $package->fresh(['reviews']);
    }

    public function activate(SpeciesPackage $package, User $actor): SpeciesPackage
    {
        if ($package->status !== SpeciesPackage::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['package' => 'Only a published package can be selected as the active version.']);
        }

        return DB::transaction(function () use ($package, $actor): SpeciesPackage {
            SpeciesPackage::query()
                ->where('species_id', $package->species_id)
                ->whereKeyNot($package->id)
                ->where('is_current', true)
                ->get()
                ->each->update(['is_current' => false]);

            $package->update(['is_current' => true]);
            $package->events()->create(['user_id' => $actor->id, 'event' => 'active_version_selected']);

            return $package->fresh(['species', 'assets']);
        });
    }

    public function restore(SpeciesPackage $package, User $actor): SpeciesPackage
    {
        if ($package->status !== SpeciesPackage::STATUS_ARCHIVED) {
            throw ValidationException::withMessages(['package' => 'Only an archived package can be restored.']);
        }

        $package->update([
            'status' => SpeciesPackage::STATUS_REVIEW,
            'is_current' => false,
            'reviewed_by' => null,
            'review_round' => ((int) $package->review_round) + 1,
            'archived_at' => null,
        ]);
        $package->events()->create(['user_id' => $actor->id, 'event' => 'restored_for_review']);

        return $package->fresh(['species', 'assets', 'reviews']);
    }

    public function approvalIsCurrent(SpeciesPackage $package, ?SpeciesPackageReview $review = null): bool
    {
        $review ??= $package->reviews()->latest('id')->first();

        return $review
            && $review->decision === 'approved'
            && $review->reviewer_id !== $package->imported_by
            && $review->review_round === (int) $package->review_round
            && $review->curriculum_alignment_confirmed
            && $review->safety_review_confirmed
            && $review->evidence_reference
            && hash_equals($review->content_fingerprint, $this->contentFingerprint($package));
    }

    public function contentFingerprint(SpeciesPackage $package): string
    {
        $package->loadMissing('assets');
        $assets = $package->assets->sortBy('asset_key')->map(fn ($asset) => [
            'key' => $asset->asset_key,
            'path' => $asset->relative_path,
            'mime' => $asset->mime_type,
            'size' => $asset->size_bytes,
            'sha256' => $asset->sha256,
        ])->values()->all();

        return hash('sha256', json_encode([
            'speciesId' => $package->species_id,
            'version' => $package->version,
            'schemaVersion' => $package->schema_version,
            'viewerProfile' => $package->viewer_profile,
            'minimumAppVersion' => $package->minimum_app_version,
            'manifest' => $package->manifest,
            'archiveChecksum' => $package->checksum,
            'sizeBytes' => $package->size_bytes,
            'assets' => $assets,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
