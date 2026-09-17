<?php

namespace App\Services;

use App\Models\SpeciesPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SpeciesCatalog
{
    /** @return array<int, array<string, mixed>> */
    public function for(User $user): array
    {
        $query = SpeciesPackage::query()->with(['species', 'assets'])->withCount('results');

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
        } elseif ($user->role === User::ROLE_INSTRUCTOR) {
            $query->where(fn (Builder $inner) => $inner
                ->published()
                ->orWhere('imported_by', $user->id));
        }

        return $query
            ->orderByRaw("CASE status WHEN 'published' THEN 1 WHEN 'review' THEN 2 WHEN 'draft' THEN 3 ELSE 4 END")
            ->orderByDesc('is_current')
            ->orderBy('species_id')
            ->get()
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

        return [
            'id' => $package->id,
            'speciesId' => $package->species_id,
            'slug' => $package->species->slug,
            'commonName' => $package->species->common_name,
            'scientificName' => $package->species->scientific_name,
            'taxonomicGroup' => $package->species->taxonomic_group,
            'description' => $package->species->description,
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
            'naturalImageUrl' => $this->assetUrl($package, 'natural'),
            'videoUrl' => $this->assetUrl($package, 'video'),
            'manifestUrl' => url("/api/species/{$package->species->slug}/packages/{$package->id}/manifest"),
            'downloadUrl' => url("/api/species-packages/{$package->id}/download"),
            'sizeBytes' => $package->size_bytes,
            'resultsCount' => $package->results_count ?? $package->results()->count(),
            'progress' => $result?->portalPayload(),
        ];
    }

    private function assetUrl(SpeciesPackage $package, string $key): ?string
    {
        $asset = $package->assets->firstWhere('asset_key', $key);

        return $asset ? url("/api/species-packages/{$package->id}/assets/{$asset->id}") : null;
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
