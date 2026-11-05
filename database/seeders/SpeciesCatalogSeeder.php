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
use Throwable;

class SpeciesCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::query()->where('role', User::ROLE_ADMIN)->first();
            $instructor = User::query()->where('role', User::ROLE_INSTRUCTOR)->where('status', User::STATUS_ACTIVE)->first();
            $student = User::query()->where('role', User::ROLE_STUDENT)->first();

            $tilapia = $this->installBundledPackage('tilapia', '1.1.0', SpeciesPackage::STATUS_PUBLISHED, $admin, true);
            $rabbitfish = $this->installBundledPackage('rabbitfish', '1.1.0', SpeciesPackage::STATUS_REVIEW, $instructor ?? $admin, false);

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

            if ($tilapia->status === SpeciesPackage::STATUS_PUBLISHED) {
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
            }

            if ($rabbitfish->status === SpeciesPackage::STATUS_REVIEW) {
                $rabbitfish->events()->firstOrCreate(
                    ['event' => 'prepared_for_faculty_review'],
                    ['user_id' => null, 'metadata' => ['source' => 'bundled', 'reviewRequired' => true]],
                );
            }
        });
    }

    protected function installBundledPackage(string $slug, string $version, string $status, ?User $actor, bool $current): SpeciesPackage
    {
        $sourceRoot = resource_path("species-packs/{$slug}/{$version}");
        $manifestPath = $sourceRoot.DIRECTORY_SEPARATOR.'manifest.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $speciesData = $manifest['species'];
        $bundleChecksum = hash_file('sha256', $manifestPath);
        if (! is_string($bundleChecksum)) {
            throw new RuntimeException("Bundled package manifest could not be hashed: {$slug} {$version}");
        }
        $bundleFiles = $this->validateBundledFiles($sourceRoot, $manifest, $slug, $version);

        $species = Species::query()->firstOrNew(['slug' => $speciesData['slug']]);
        if (! $species->exists) {
            $species->fill([
                'common_name' => $speciesData['commonName'],
                'scientific_name' => $speciesData['scientificName'],
                'taxonomic_group' => $speciesData['taxonomicGroup'],
                'activity_mode' => $manifest['activityMode'],
                'description' => $slug === 'tilapia'
                    ? 'The published flagship guided anatomy and virtual-dissection specimen.'
                    : 'A complete Goldspotted Rabbitfish lesson awaiting independent faculty review and administrator publication.',
            ])->save();
        } elseif ($species->common_name !== $speciesData['commonName'] || $species->scientific_name !== $speciesData['scientificName']) {
            throw new RuntimeException("Bundled package {$slug} {$version} conflicts with the installed species identity.");
        }

        $existing = SpeciesPackage::query()
            ->where('species_id', $species->id)
            ->where('version', $manifest['packageVersion'])
            ->first();
        if ($existing) {
            if (! is_string($existing->checksum) || ! hash_equals(strtolower($existing->checksum), strtolower($bundleChecksum))) {
                throw new RuntimeException("Bundled package {$slug} {$version} differs from the installed immutable version. Create a new package version instead.");
            }

            $this->verifyStoredBundle($existing, $manifest, $bundleFiles, $slug, $version);

            return $existing->load(['species', 'assets', 'reviews']);
        }

        if ($current) {
            SpeciesPackage::query()
                ->where('species_id', $species->id)
                ->where('version', '<>', $manifest['packageVersion'])
                ->where('is_current', true)
                ->get()
                ->each(fn (SpeciesPackage $package) => $package->update(['is_current' => false]));
        }

        $storagePrefix = "species-packages/{$slug}/{$manifest['packageVersion']}";
        $disk = Storage::disk(config('species-packages.disk'));
        $totalSize = filesize($manifestPath) ?: 0;
        foreach ($bundleFiles as $relativePath => $fileState) {
            $source = $sourceRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $contents = (string) file_get_contents($source);
            $totalSize += $fileState['size'];
            $storedPath = "{$storagePrefix}/{$relativePath}";
            if ($disk->exists($storedPath)) {
                [$storedSize, $storedHash] = $this->storedFileState($storedPath, $slug, $version);
                if ($storedSize !== $fileState['size'] || ! hash_equals($fileState['sha256'], $storedHash)) {
                    throw new RuntimeException("Bundled package {$slug} {$version} found conflicting versioned storage: {$relativePath}.");
                }
            } else {
                $disk->put($storedPath, $contents);
            }
        }
        $storedManifestPath = "{$storagePrefix}/manifest.json";
        if ($disk->exists($storedManifestPath)) {
            try {
                $storedManifest = json_decode((string) $disk->get($storedManifestPath), true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                throw new RuntimeException("Bundled package {$slug} {$version} found an invalid stored manifest.", previous: $exception);
            }
            if ($storedManifest != $manifest) {
                throw new RuntimeException("Bundled package {$slug} {$version} found a conflicting stored manifest.");
            }
        } else {
            $disk->put($storedManifestPath, (string) file_get_contents($manifestPath));
        }

        $package = SpeciesPackage::withoutEvents(function () use ($species, $manifest, $status, $actor, $current, $storagePrefix, $totalSize, $bundleChecksum): SpeciesPackage {
            $package = new SpeciesPackage([
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
                'checksum' => $bundleChecksum,
                'size_bytes' => $totalSize,
                'is_current' => $current,
                'imported_by' => $actor?->id,
                'reviewed_by' => $status === SpeciesPackage::STATUS_PUBLISHED ? $actor?->id : null,
                'review_round' => $status === SpeciesPackage::STATUS_REVIEW ? 1 : 0,
                'published_at' => $status === SpeciesPackage::STATUS_PUBLISHED ? now() : null,
                'archived_at' => null,
            ]);
            $package->save();

            return $package;
        });
        app(DelayedCloneRecorder::class)->record($package, CloneOutbox::OPERATION_CREATE);

        $packageFiles = $manifest['assets'];
        foreach ($manifest['content'] as $contentKey => $relativePath) {
            $packageFiles["content.{$contentKey}"] = $relativePath;
        }
        foreach ($packageFiles as $assetKey => $relativePath) {
            $source = $sourceRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $asset = new SpeciesPackageAsset(['species_package_id' => $package->id, 'asset_key' => $assetKey]);
            SpeciesPackageAsset::withoutEvents(function () use ($asset, $relativePath, $source, $bundleFiles): void {
                $asset->fill([
                    'relative_path' => $relativePath,
                    'mime_type' => $this->mimeType($source),
                    'size_bytes' => $bundleFiles[$relativePath]['size'],
                    'sha256' => $bundleFiles[$relativePath]['sha256'],
                ]);
                $asset->save();
            });
            app(DelayedCloneRecorder::class)->record($asset, CloneOutbox::OPERATION_CREATE);
        }
        $this->verifyStoredBundle($package->fresh('assets'), $manifest, $bundleFiles, $slug, $version);

        return $package;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, array{size: int, sha256: string}>
     */
    protected function validateBundledFiles(string $sourceRoot, array $manifest, string $slug, string $version): array
    {
        $declaredFiles = $manifest['files'] ?? null;
        if (! is_array($declaredFiles) || $declaredFiles === []) {
            throw new RuntimeException("Bundled package {$slug} {$version} has no file checksum declarations.");
        }

        $files = [];
        foreach ($declaredFiles as $relativePath => $expectedHash) {
            if (! is_string($relativePath)
                || ! $this->isSafeRelativePath($relativePath)
                || ! is_string($expectedHash)
                || ! preg_match('/^[a-f0-9]{64}$/i', $expectedHash)) {
                throw new RuntimeException("Bundled package {$slug} {$version} has an invalid file checksum declaration.");
            }

            $source = $sourceRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (! is_file($source)) {
                throw new RuntimeException("Bundled package file is missing: {$relativePath}");
            }
            $actualHash = hash_file('sha256', $source);
            $size = filesize($source);
            if (! is_string($actualHash) || $size === false || ! hash_equals(strtolower($expectedHash), strtolower($actualHash))) {
                throw new RuntimeException("Bundled package {$slug} {$version} source checksum mismatch: {$relativePath}. Create a new package version instead.");
            }

            $files[$relativePath] = ['size' => (int) $size, 'sha256' => strtolower($actualHash)];
        }

        return $files;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, array{size: int, sha256: string}>  $bundleFiles
     */
    private function verifyStoredBundle(SpeciesPackage $package, array $manifest, array $bundleFiles, string $slug, string $version): void
    {
        $disk = Storage::disk(config('species-packages.disk'));
        $storagePrefix = trim((string) $package->archive_path, '/');
        if ($storagePrefix === '') {
            throw new RuntimeException("Bundled package {$slug} {$version} has no stored archive path.");
        }

        $manifestPath = "{$storagePrefix}/manifest.json";
        try {
            $storedManifest = json_decode((string) $disk->get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException("Bundled package {$slug} {$version} stored manifest is missing or invalid.", previous: $exception);
        }
        if ($storedManifest != $manifest) {
            throw new RuntimeException("Bundled package {$slug} {$version} stored manifest differs from its immutable source.");
        }

        foreach ($bundleFiles as $relativePath => $expected) {
            [$storedSize, $storedHash] = $this->storedFileState("{$storagePrefix}/{$relativePath}", $slug, $version);
            if ($storedSize !== $expected['size'] || ! hash_equals($expected['sha256'], $storedHash)) {
                throw new RuntimeException("Bundled package {$slug} {$version} stored file differs from its immutable source: {$relativePath}.");
            }
        }

        $assetMap = $manifest['assets'];
        foreach ($manifest['content'] as $contentKey => $relativePath) {
            $assetMap["content.{$contentKey}"] = $relativePath;
        }
        $storedAssets = $package->assets()->get()->keyBy('asset_key');
        if ($storedAssets->count() !== count($assetMap)) {
            throw new RuntimeException("Bundled package {$slug} {$version} stored asset records are incomplete.");
        }
        foreach ($assetMap as $assetKey => $relativePath) {
            $asset = $storedAssets->get($assetKey);
            $expected = $bundleFiles[$relativePath] ?? null;
            if (! $asset || ! $expected
                || $asset->relative_path !== $relativePath
                || (int) $asset->size_bytes !== $expected['size']
                || ! is_string($asset->sha256)
                || ! hash_equals(strtolower($asset->sha256), $expected['sha256'])) {
                throw new RuntimeException("Bundled package {$slug} {$version} stored asset record differs from its immutable source: {$assetKey}.");
            }
        }
    }

    /** @return array{int, string} */
    private function storedFileState(string $path, string $slug, string $version): array
    {
        $disk = Storage::disk(config('species-packages.disk'));
        try {
            if (! $disk->exists($path)) {
                throw new RuntimeException("Bundled package {$slug} {$version} stored file is missing: {$path}.");
            }
            $size = $disk->size($path);
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) {
                throw new RuntimeException("Bundled package {$slug} {$version} stored file is unreadable: {$path}.");
            }
            try {
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stream);
                $sha256 = hash_final($hash);
            } finally {
                fclose($stream);
            }
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException("Bundled package {$slug} {$version} stored file could not be verified: {$path}.", previous: $exception);
        }

        return [(int) $size, strtolower($sha256)];
    }

    private function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
            return false;
        }

        return collect(explode('/', $path))->every(fn (string $segment) => $segment !== '' && $segment !== '.' && $segment !== '..');
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
