<?php

namespace App\Services;

use App\Models\LessonResult;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
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

        $package->update(['status' => SpeciesPackage::STATUS_REVIEW]);
        $package->events()->create(['user_id' => $actor->id, 'event' => 'submitted_for_review']);

        return $package->fresh(['species', 'assets']);
    }

    /** @return array{package: SpeciesPackage, upgradedAssignments: int, pinnedAssignments: int} */
    public function publish(SpeciesPackage $package, User $actor): array
    {
        if (! in_array($package->status, [SpeciesPackage::STATUS_DRAFT, SpeciesPackage::STATUS_REVIEW], true)) {
            throw ValidationException::withMessages(['package' => 'Only a draft or review package can be published.']);
        }

        return DB::transaction(function () use ($package, $actor): array {
            SpeciesPackage::query()
                ->where('species_id', $package->species_id)
                ->whereKeyNot($package->id)
                ->where('is_current', true)
                ->get()
                ->each->update(['is_current' => false]);

            $package->update([
                'status' => SpeciesPackage::STATUS_PUBLISHED,
                'is_current' => true,
                'reviewed_by' => $actor->id,
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
                        ->whereIn('user_id', $studentIds)
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

            return ['package' => $package->fresh(['species', 'assets']), 'upgradedAssignments' => $upgraded, 'pinnedAssignments' => $pinned];
        });
    }

    public function archive(SpeciesPackage $package, User $actor): SpeciesPackage
    {
        if ($package->status !== SpeciesPackage::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['package' => 'Only a published package can be archived.']);
        }

        $package->update(['status' => SpeciesPackage::STATUS_ARCHIVED, 'is_current' => false, 'archived_at' => now()]);
        $package->events()->create(['user_id' => $actor->id, 'event' => 'archived']);

        return $package->fresh();
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

        $package->update(['status' => SpeciesPackage::STATUS_REVIEW, 'archived_at' => null]);
        $package->events()->create(['user_id' => $actor->id, 'event' => 'restored_for_review']);

        return $this->publish($package, $actor)['package'];
    }
}
