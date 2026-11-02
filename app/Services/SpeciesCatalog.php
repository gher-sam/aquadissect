<?php

namespace App\Services;

use App\Models\SpeciesPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SpeciesCatalog
{
    public function __construct(
        private readonly LearningModuleGate $gate,
        private readonly SpeciesPackageLifecycle $lifecycle,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function for(User $user): array
    {
        $query = SpeciesPackage::query()->with(['species', 'assets', 'reviews'])->withCount(['results', 'reviews']);

        $visibleSlugs = config('species-packages.visible_slugs', ['rabbitfish']);
        if ($visibleSlugs !== []) {
            $query->whereHas('species', fn (Builder $species) => $species->whereIn('slug', $visibleSlugs));
        }

        if ($user->role === User::ROLE_STUDENT) {
            $packageIds = $user->classEnrollments()
                ->where('class_enrollments.status', 'active')
                ->whereHas('learningClass', fn ($query) => $query->where('status', 'active'))
                ->with('learningClass.assignments')
                ->get()
                ->flatMap(fn ($enrollment) => $enrollment->learningClass->assignments
                    ->where('active', true)
                    ->pluck('species_package_id'))
                ->unique();
            $query->whereIn('id', $packageIds)->published();
        } elseif ($user->role === User::ROLE_PROGRAM_HEAD) {
            $query->whereIn('status', [SpeciesPackage::STATUS_PUBLISHED, SpeciesPackage::STATUS_REVIEW]);
        } elseif ($user->role === User::ROLE_INSTRUCTOR) {
            $query->where(fn (Builder $inner) => $inner
                ->published()
                ->orWhere('imported_by', $user->id)
                ->orWhere('status', SpeciesPackage::STATUS_REVIEW));
        }

        $statusOrder = [
            SpeciesPackage::STATUS_PUBLISHED => 1,
            SpeciesPackage::STATUS_REVIEW => 2,
            SpeciesPackage::STATUS_DRAFT => 3,
            SpeciesPackage::STATUS_ARCHIVED => 4,
        ];

        return $query->get()
            ->sort(function (SpeciesPackage $left, SpeciesPackage $right) use ($statusOrder): int {
                $comparison = ($statusOrder[$left->status] ?? 5) <=> ($statusOrder[$right->status] ?? 5);
                if ($comparison !== 0) {
                    return $comparison;
                }

                $comparison = ((int) $right->is_current) <=> ((int) $left->is_current);
                if ($comparison !== 0) {
                    return $comparison;
                }

                $comparison = $left->species_id <=> $right->species_id;
                if ($comparison !== 0) {
                    return $comparison;
                }

                $comparison = version_compare($right->version, $left->version);

                return $comparison !== 0 ? $comparison : $right->id <=> $left->id;
            })
            ->map(fn (SpeciesPackage $package) => $this->packagePayload($package, $user))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function packagePayload(SpeciesPackage $package, ?User $user = null): array
    {
        $manifest = $package->manifest;
        $result = $user?->lessonResults()
            ->where('species_package_id', $package->id)
            ->latest('completed_at')
            ->first();
        $currentPackageId = SpeciesPackage::query()
            ->where('species_id', $package->species_id)
            ->published()
            ->where('is_current', true)
            ->value('id');
        $classIds = collect();
        $availableClassId = null;
        if ($user?->role === User::ROLE_STUDENT) {
            $classIds = $user->classEnrollments()->where('class_enrollments.status', 'active')
                ->whereHas('learningClass.assignments', fn ($query) => $query
                    ->where('species_package_id', $package->id)->where('active', true))
                ->pluck('class_id')->map(fn ($id) => (int) $id)->unique()->values();
            $availableClassId = $classIds->first(fn (int $classId) => $this->gate->status($user, $classId)['satisfied']);
        }
        $locked = $user?->role === User::ROLE_STUDENT && $classIds->isNotEmpty() && $availableClassId === null;

        $staffPayload = [];
        if ($user && $user->role !== User::ROLE_STUDENT) {
            $latestReview = $package->reviews->sortByDesc('id')->first();
            $approvalCurrent = $this->lifecycle->approvalIsCurrent($package, $latestReview);
            $staffPayload = [
                'importedBy' => $package->imported_by,
                'reviewCount' => $package->reviews_count ?? $package->reviews->count(),
                'approvalCurrent' => (bool) $approvalCurrent,
                'latestReview' => $latestReview ? [
                    'decision' => $latestReview->decision,
                    'reviewerRole' => $latestReview->reviewer_role,
                    'notes' => $latestReview->notes,
                    'evidenceReference' => $latestReview->evidence_reference,
                    'curriculumAlignmentConfirmed' => $latestReview->curriculum_alignment_confirmed,
                    'safetyReviewConfirmed' => $latestReview->safety_review_confirmed,
                    'recordedAt' => $latestReview->created_at?->toIso8601String(),
                ] : null,
            ];
        }

        return [
            'id' => $package->id,
            'speciesId' => $package->species_id,
            'slug' => $package->species->slug,
            'commonName' => $package->species->common_name,
            'scientificName' => $package->species->scientific_name,
            'taxonomicGroup' => $package->species->taxonomic_group,
            'description' => $manifest['speciesProfile']['description'] ?? $manifest['species']['description'] ?? $package->species->description,
            'activityMode' => $package->species->activity_mode,
            'version' => $package->version,
            'status' => $package->status,
            'isCurrent' => $package->is_current,
            'updateStatus' => $package->status !== SpeciesPackage::STATUS_PUBLISHED
                ? 'Awaiting publication'
                : ($currentPackageId === $package->id ? 'Current version' : 'Pinned to started assignment'),
            'viewerProfile' => $package->viewer_profile,
            'has3d' => isset($manifest['viewer']['optional3d']) || isset($manifest['assets']['model']),
            'hasVideo' => isset($manifest['assets']['video']),
            'draftNotice' => $manifest['draftNotice'] ?? null,
            'lessonKey' => $manifest['lessonKey'],
            'duration' => $this->lessonDuration($package),
            'cardPreviewVideoUrl' => ! $locked && $package->species->slug === 'rabbitfish' && $package->version === '1.1.0'
                ? url("/api/species-packages/{$package->id}/card-preview").($availableClassId ? '?classId='.$availableClassId : '') : null,
            'naturalImageUrl' => $locked ? null : $this->assetUrl($package, 'natural', $availableClassId),
            'videoUrl' => $locked ? null : $this->assetUrl($package, 'video', $availableClassId),
            'manifestUrl' => url("/api/species/{$package->species->slug}/packages/{$package->id}/manifest"),
            'downloadUrl' => url("/api/species-packages/{$package->id}/download"),
            'sizeBytes' => $package->size_bytes,
            'resultsCount' => $package->results_count ?? $package->results()->count(),
            'progress' => $result?->portalPayload(),
            'locked' => $locked,
            'availableClassId' => $availableClassId,
            'lockReason' => $locked
                ? 'Complete Safety & Tools or use an instructor-approved exception.' : null,
            ...$staffPayload,
        ];
    }

    private function assetUrl(SpeciesPackage $package, string $key, ?int $classId = null): ?string
    {
        $asset = $package->assets->firstWhere('asset_key', $key);

        return $asset ? url("/api/species-packages/{$package->id}/assets/{$asset->id}").($classId ? '?classId='.$classId : '') : null;
    }

    private function lessonDuration(SpeciesPackage $package): ?string
    {
        $path = storage_path('app/private/'.trim((string) $package->archive_path, '/').'/'.($package->manifest['content']['lesson'] ?? ''));
        if (! is_file($path)) {
            return null;
        }

        $lesson = json_decode((string) file_get_contents($path), true);

        return $lesson['duration'] ?? null;
    }
}
