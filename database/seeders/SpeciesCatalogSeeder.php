<?php

namespace Database\Seeders;

use App\Models\ClassEnrollment;
use App\Models\ClassInstructor;
use App\Models\CloneOutbox;
use App\Models\LearningClass;
use App\Models\LessonResult;
use App\Models\Species;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use App\Models\SpeciesPackageAsset;
use App\Models\User;
use App\Services\DelayedCloneRecorder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SpeciesCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::query()->where('role', User::ROLE_ADMIN)->first();
            $instructor = User::query()->where('role', User::ROLE_INSTRUCTOR)->where('status', User::STATUS_ACTIVE)->first();
            $student = User::query()->where('role', User::ROLE_STUDENT)->first();

            $tilapia = $this->installBundledPackage('tilapia', '1.1.0', SpeciesPackage::STATUS_PUBLISHED, $admin, true);
            $rabbitfish = $this->installBundledPackage('rabbitfish', '1.0.0', SpeciesPackage::STATUS_REVIEW, $instructor ?? $admin, false);

            $class = LearningClass::query()->updateOrCreate(
                ['code' => 'FISHBIO-101'],
                ['name' => 'Fish Biology 101', 'status' => 'active'],
            );

            if ($instructor) {
                ClassInstructor::query()->firstOrCreate(['class_id' => $class->id, 'user_id' => $instructor->id]);
            }

            if ($student) {
                ClassEnrollment::query()->updateOrCreate(
                    ['class_id' => $class->id, 'user_id' => $student->id],
                    ['status' => 'active'],
                );
            }

            $assignment = SpeciesAssignment::query()->firstOrNew([
                'class_id' => $class->id,
                'species_id' => $tilapia->species_id,
            ]);
            $hasStarted = $assignment->exists && LessonResult::query()
                ->where('species_package_id', $assignment->species_package_id)
                ->whereIn('user_id', $class->enrollments()->where('status', 'active')->pluck('user_id'))
                ->exists();
            if (! $hasStarted) {
                $assignment->fill([
                    'species_package_id' => $tilapia->id,
                    'assigned_by' => $instructor?->id,
                    'assigned_at' => now(),
                    'active' => true,
                ])->save();
            }

            $rabbitfish->events()->firstOrCreate(
                ['event' => 'prepared_for_faculty_review'],
                ['user_id' => null, 'metadata' => ['source' => 'bundled', 'reviewRequired' => true]],
            );
        });
    }

    private function installBundledPackage(string $slug, string $version, string $status, ?User $actor, bool $current): SpeciesPackage
    {
        $sourceRoot = resource_path("species-packs/{$slug}/{$version}");
        $manifestPath = $sourceRoot.DIRECTORY_SEPARATOR.'manifest.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $speciesData = $manifest['species'];

        $species = Species::query()->updateOrCreate(
            ['slug' => $speciesData['slug']],
            [
                'common_name' => $speciesData['commonName'],
                'scientific_name' => $speciesData['scientificName'],
                'taxonomic_group' => $speciesData['taxonomicGroup'],
                'activity_mode' => $manifest['activityMode'],
                'description' => $slug === 'tilapia'
                    ? 'The published flagship guided anatomy and virtual-dissection specimen.'
                    : 'A complete Goldspotted Rabbitfish lesson awaiting independent faculty review and administrator publication.',
            ],
        );

        if ($current) {
            SpeciesPackage::query()
                ->where('species_id', $species->id)
                ->where('version', '<>', $manifest['packageVersion'])
                ->where('is_current', true)
                ->get()
                ->each(fn (SpeciesPackage $package) => $package->update(['is_current' => false]));
        }

        $storagePrefix = "species-packages/{$slug}/{$manifest['packageVersion']}";
        $totalSize = filesize($manifestPath) ?: 0;
        foreach (array_keys($manifest['files']) as $relativePath) {
            $source = $sourceRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (! is_file($source)) {
                throw new RuntimeException("Bundled package file is missing: {$relativePath}");
            }

            $contents = (string) file_get_contents($source);
            $totalSize += strlen($contents);
            Storage::disk(config('species-packages.disk'))->put("{$storagePrefix}/{$relativePath}", $contents);
        }
        Storage::disk(config('species-packages.disk'))->put("{$storagePrefix}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $operation = SpeciesPackage::query()->where('species_id', $species->id)->where('version', $manifest['packageVersion'])->exists()
            ? CloneOutbox::OPERATION_UPDATE
            : CloneOutbox::OPERATION_CREATE;
        $package = SpeciesPackage::withoutEvents(function () use ($species, $manifest, $status, $actor, $current, $storagePrefix, $totalSize): SpeciesPackage {
            $package = SpeciesPackage::query()->firstOrNew([
                'species_id' => $species->id,
                'version' => $manifest['packageVersion'],
            ]);
            $package->fill([
                'schema_version' => $manifest['schemaVersion'],
                'status' => $status,
                'viewer_profile' => $manifest['viewer']['profile'],
                'minimum_app_version' => $manifest['minimumApplicationVersion'],
                'manifest' => $manifest,
                'archive_path' => $storagePrefix,
                'checksum' => hash_file('sha256', resource_path("species-packs/{$species->slug}/{$manifest['packageVersion']}/manifest.json")),
                'size_bytes' => $totalSize,
                'is_current' => $current,
                'imported_by' => $package->imported_by ?: $actor?->id,
                'reviewed_by' => $status === SpeciesPackage::STATUS_PUBLISHED ? ($package->reviewed_by ?: $actor?->id) : null,
                'review_round' => $status === SpeciesPackage::STATUS_REVIEW ? max(1, (int) $package->review_round) : (int) $package->review_round,
                'published_at' => $status === SpeciesPackage::STATUS_PUBLISHED ? ($package->published_at ?: now()) : null,
                'archived_at' => null,
            ]);
            $package->save();

            return $package;
        });
        app(DelayedCloneRecorder::class)->record($package, $operation);

        $packageFiles = $manifest['assets'];
        foreach ($manifest['content'] as $contentKey => $relativePath) {
            $packageFiles["content.{$contentKey}"] = $relativePath;
        }
        foreach ($packageFiles as $assetKey => $relativePath) {
            $source = $sourceRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $asset = SpeciesPackageAsset::query()->firstOrNew(['species_package_id' => $package->id, 'asset_key' => $assetKey]);
            $assetOperation = $asset->exists ? CloneOutbox::OPERATION_UPDATE : CloneOutbox::OPERATION_CREATE;
            SpeciesPackageAsset::withoutEvents(function () use ($asset, $relativePath, $source): void {
                $asset->fill([
                    'relative_path' => $relativePath,
                    'mime_type' => $this->mimeType($source),
                    'size_bytes' => filesize($source),
                    'sha256' => hash_file('sha256', $source),
                ]);
                $asset->save();
            });
            app(DelayedCloneRecorder::class)->record($asset, $assetOperation);
        }

        return $package;
    }

    private function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'glb' => 'model/gltf-binary',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            default => 'application/octet-stream',
        };
    }
}
