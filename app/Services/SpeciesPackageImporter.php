<?php

namespace App\Services;

use App\Models\Species;
use App\Models\SpeciesPackage;
use App\Models\SpeciesPackageAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use ZipArchive;

class SpeciesPackageImporter
{
    /** @var array<string, true> */
    private array $entries = [];

    /** @var array<string, true> */
    private array $seenEntries = [];

    private int $expandedBytes = 0;

    public function import(UploadedFile $upload, User $actor): SpeciesPackage
    {
        $this->entries = [];
        $this->seenEntries = [];
        $this->expandedBytes = 0;

        if ($upload->getSize() === false || $upload->getSize() > config('species-packages.max_archive_bytes')) {
            $this->fail('archive', 'The package archive must not exceed 100 MB.');
        }

        $token = (string) Str::uuid();
        $quarantineRoot = storage_path("app/private/species-quarantine/{$token}");
        $archivePath = $quarantineRoot.DIRECTORY_SEPARATOR.'upload.aqpack';
        $extractRoot = $quarantineRoot.DIRECTORY_SEPARATOR.'extracted';
        File::ensureDirectoryExists($extractRoot);
        File::copy($upload->getRealPath(), $archivePath);

        try {
            $manifest = $this->extractAndValidate($archivePath, $extractRoot);
            $slug = $manifest['species']['slug'];
            $version = $manifest['packageVersion'];
            $targetRoot = storage_path("app/private/species-packages/{$slug}/{$version}");

            if (SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', $slug))->where('version', $version)->exists() || File::isDirectory($targetRoot)) {
                $this->fail('packageVersion', 'That species package version already exists. Versions are immutable.');
            }

            File::ensureDirectoryExists(dirname($targetRoot));
            if (! @rename($extractRoot, $targetRoot)) {
                throw new \RuntimeException('The validated package could not be moved into versioned storage.');
            }

            try {
                return DB::transaction(function () use ($manifest, $actor, $targetRoot): SpeciesPackage {
                    $speciesData = $manifest['species'];
                    $species = Species::query()->firstOrCreate(
                        ['slug' => $speciesData['slug']],
                        [
                            'common_name' => $speciesData['commonName'],
                            'scientific_name' => $speciesData['scientificName'],
                            'taxonomic_group' => $speciesData['taxonomicGroup'],
                            'activity_mode' => $manifest['activityMode'],
                            'description' => $speciesData['description'] ?? null,
                        ],
                    );

                    if ($species->common_name !== $speciesData['commonName'] || $species->scientific_name !== $speciesData['scientificName']) {
                        $this->fail('species', 'The species identity conflicts with the existing catalog record.');
                    }

                    $storagePrefix = "species-packages/{$species->slug}/{$manifest['packageVersion']}";
                    $package = SpeciesPackage::query()->create([
                        'species_id' => $species->id,
                        'version' => $manifest['packageVersion'],
                        'schema_version' => $manifest['schemaVersion'],
                        'status' => SpeciesPackage::STATUS_DRAFT,
                        'viewer_profile' => $manifest['viewer']['profile'],
                        'minimum_app_version' => $manifest['minimumApplicationVersion'],
                        'manifest' => $manifest,
                        'archive_path' => $storagePrefix,
                        'checksum' => hash_file('sha256', $targetRoot.DIRECTORY_SEPARATOR.'manifest.json'),
                        'size_bytes' => $this->expandedBytes,
                        'is_current' => false,
                        'imported_by' => $actor->id,
                    ]);

                    $packageFiles = $manifest['assets'];
                    foreach ($manifest['content'] as $contentKey => $relativePath) {
                        $packageFiles["content.{$contentKey}"] = $relativePath;
                    }
                    foreach ($packageFiles as $assetKey => $relativePath) {
                        $absolutePath = $targetRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                        SpeciesPackageAsset::query()->create([
                            'species_package_id' => $package->id,
                            'asset_key' => $assetKey,
                            'relative_path' => $relativePath,
                            'mime_type' => $this->mimeType($absolutePath),
                            'size_bytes' => filesize($absolutePath),
                            'sha256' => hash_file('sha256', $absolutePath),
                        ]);
                    }

                    $package->events()->create([
                        'user_id' => $actor->id,
                        'event' => 'draft_imported',
                        'metadata' => ['checksum' => $package->checksum, 'sourceName' => 'uploaded-aqpack'],
                    ]);

                    return $package->fresh(['species', 'assets']);
                });
            } catch (Throwable $exception) {
                File::deleteDirectory($targetRoot);
                throw $exception;
            }
        } finally {
            File::deleteDirectory($quarantineRoot);
        }
    }

    /** @return array<string, mixed> */
    private function extractAndValidate(string $archivePath, string $extractRoot): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CHECKCONS) !== true) {
            $this->fail('archive', 'The uploaded file is not a valid ZIP-based .aqpack archive.');
        }

        try {
            if ($zip->numFiles > config('species-packages.max_files')) {
                $this->fail('archive', 'The archive contains too many entries.');
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = (string) ($stat['name'] ?? '');
                $isDirectory = str_ends_with($name, '/');
                $this->validatePath($name, $isDirectory);

                $canonical = strtolower(rtrim($name, '/'));
                if (isset($this->seenEntries[$canonical])) {
                    $this->fail('archive', "The archive contains a duplicate entry: {$name}");
                }
                $this->seenEntries[$canonical] = true;

                if ($isDirectory) {
                    continue;
                }
                $this->entries[$canonical] = true;

                $this->rejectLink($zip, $index, $name);
                $this->validateExtension($name);
                $this->expandedBytes += (int) ($stat['size'] ?? 0);
                if ($this->expandedBytes > config('species-packages.max_expanded_bytes')) {
                    $this->fail('archive', 'The expanded archive would exceed 250 MB.');
                }

                $target = $extractRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
                File::ensureDirectoryExists(dirname($target));
                $source = $zip->getStream($name);
                $destination = fopen($target, 'wb');
                if (! is_resource($source) || ! is_resource($destination)) {
                    $this->fail('archive', "The archive entry could not be read: {$name}");
                }
                stream_copy_to_stream($source, $destination);
                fclose($source);
                fclose($destination);
            }
        } finally {
            $zip->close();
        }

        foreach (config('species-packages.required_files') as $required) {
            if (! isset($this->entries[strtolower($required)])) {
                $this->fail('archive', "The package is missing {$required}.");
            }
        }

        return $this->validateManifest($extractRoot);
    }

    private function validatePath(string $path, bool $isDirectory): void
    {
        $trimmed = rtrim($path, '/');
        if ($trimmed === '' && $isDirectory) {
            $this->fail('archive', 'The archive contains an invalid empty path.');
        }

        if (str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) || str_contains($path, "\0")) {
            $this->fail('archive', "The archive contains an unsafe path: {$path}");
        }

        foreach (explode('/', $trimmed) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                $this->fail('archive', "The archive contains a traversal or invalid path: {$path}");
            }
        }
    }

    private function rejectLink(ZipArchive $zip, int $index, string $name): void
    {
        $attributes = 0;
        $operations = 0;
        if ($zip->getExternalAttributesIndex($index, $operations, $attributes)) {
            $type = ($attributes >> 16) & 0170000;
            if ($type === 0120000) {
                $this->fail('archive', "Symbolic links are not allowed: {$name}");
            }
        }
    }

    private function validateExtension(string $path): void
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, config('species-packages.allowed_extensions'), true)) {
            $this->fail('archive', "The archive contains a forbidden file type: {$path}");
        }
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

    /** @return array<string, mixed> */
    private function validateManifest(string $root): array
    {
        $manifest = $this->decodeJson($root, 'manifest.json');
        foreach (['schemaVersion', 'packageVersion', 'minimumApplicationVersion', 'species', 'lessonKey', 'activityMode', 'viewer', 'safetyNotices', 'references', 'licenses', 'assets', 'content', 'files'] as $field) {
            if (! array_key_exists($field, $manifest)) {
                $this->fail('manifest', "The manifest is missing {$field}.");
            }
        }

        if (! is_array($manifest['viewer']) || ! is_array($manifest['assets']) || ! is_array($manifest['content']) || ! is_array($manifest['files'])) {
            $this->fail('manifest', 'Viewer, assets, content, and files must be JSON objects.');
        }

        if ($manifest['schemaVersion'] !== '1.0' || ! preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?$/', (string) $manifest['packageVersion'])) {
            $this->fail('manifest', 'The schema version or immutable semantic package version is invalid.');
        }
        if (! preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', (string) $manifest['minimumApplicationVersion'])
            || version_compare(config('aquadissect.version'), $manifest['minimumApplicationVersion'], '<')) {
            $this->fail('manifest', 'This package requires a newer AquaDissect application version.');
        }

        if (! is_array($manifest['species'])) {
            $this->fail('manifest', 'The species identity must be a JSON object.');
        }
        foreach (['slug', 'commonName', 'scientificName', 'taxonomicGroup'] as $field) {
            if (! is_string($manifest['species'][$field] ?? null) || trim($manifest['species'][$field]) === '') {
                $this->fail('manifest', "The species identity is missing {$field}.");
            }
        }
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $manifest['species']['slug'])) {
            $this->fail('manifest', 'The species slug must use lowercase letters, numbers, and hyphens.');
        }
        if (! in_array($manifest['activityMode'], config('species-packages.activity_modes'), true)) {
            $this->fail('manifest', 'The activity mode is not supported.');
        }
        if (! is_array($manifest['viewer']) || ! is_array($manifest['assets']) || ! is_array($manifest['content']) || ! is_array($manifest['files'])) {
            $this->fail('manifest', 'Viewer, asset, content, and file declarations must be JSON objects.');
        }
        if (($manifest['viewer']['profile'] ?? null) !== 'layered-2d') {
            $this->fail('manifest', 'Uploaded packages must use the built-in layered-2d viewer profile.');
        }
        if (isset($manifest['viewer']['optional3d']['adapter'])) {
            $this->fail('manifest', 'Uploaded packages cannot select executable viewer adapters. Include a data-only GLB model instead.');
        }
        if (! is_array($manifest['safetyNotices']) || $manifest['safetyNotices'] === [] || ! is_array($manifest['references']) || $manifest['references'] === [] || ! is_array($manifest['licenses']) || $manifest['licenses'] === []) {
            $this->fail('manifest', 'Safety notices, references, and licences are required.');
        }

        foreach (['natural', 'external', 'internal'] as $assetKey) {
            $assetPath = $manifest['assets'][$assetKey] ?? null;
            if (! is_string($assetPath) || ! preg_match('/\.(png|jpe?g|webp)$/i', $assetPath)) {
                $this->fail('manifest', "The {$assetKey} 2.5D image is required.");
            }
        }
        if (isset($manifest['assets']['model']) && ! preg_match('/\.glb$/i', (string) $manifest['assets']['model'])) {
            $this->fail('manifest', 'An optional 3D model must be a GLB file.');
        }
        if (isset($manifest['assets']['video']) && ! preg_match('/\.(mp4|webm)$/i', (string) $manifest['assets']['video'])) {
            $this->fail('manifest', 'An optional natural-specimen video must be an MP4 or WebM file.');
        }

        foreach (['anatomy', 'lesson', 'quiz'] as $contentKey) {
            if (! is_string($manifest['content'][$contentKey] ?? null) || ! str_ends_with(strtolower($manifest['content'][$contentKey]), '.json')) {
                $this->fail('manifest', "The {$contentKey} JSON content file is required.");
            }
        }

        $fileEntries = array_filter(array_keys($this->entries), fn (string $entry) => $entry !== 'manifest.json');
        $declaredFiles = array_change_key_case($manifest['files'], CASE_LOWER);
        sort($fileEntries);
        $declaredNames = array_keys($declaredFiles);
        sort($declaredNames);
        if ($fileEntries !== $declaredNames) {
            $this->fail('manifest', 'The manifest hashes must declare every file and no unknown files.');
        }

        foreach ($declaredFiles as $relativePath => $expectedHash) {
            if (! is_string($expectedHash) || ! preg_match('/^[a-f0-9]{64}$/i', $expectedHash)) {
                $this->fail('manifest', "The checksum for {$relativePath} is invalid.");
            }
            $actualHash = hash_file('sha256', $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
            if (! hash_equals(strtolower($expectedHash), strtolower($actualHash))) {
                $this->fail('manifest', "The checksum for {$relativePath} does not match.");
            }
        }

        foreach ($manifest['assets'] as $assetPath) {
            if (! isset($declaredFiles[strtolower($assetPath)])) {
                $this->fail('manifest', "An asset is missing from the file hash list: {$assetPath}");
            }
            if (preg_match('/\.(png|jpe?g|webp)$/i', $assetPath) && @getimagesize($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $assetPath)) === false) {
                $this->fail('manifest', "An image asset is invalid: {$assetPath}");
            }
            if (preg_match('/\.(mp4|webm)$/i', $assetPath)) {
                $videoPath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $assetPath);
                $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($videoPath);
                if (! in_array($detectedMime, config('species-packages.allowed_video_mime_types'), true)) {
                    $this->fail('manifest', "A video asset has an invalid media type: {$assetPath}");
                }
                if ((filesize($videoPath) ?: 0) > config('species-packages.max_video_bytes')) {
                    $this->fail('manifest', "A video asset exceeds the 80 MB limit: {$assetPath}");
                }
            }
        }

        $anatomy = $this->decodeJson($root, $manifest['content']['anatomy']);
        $lesson = $this->decodeJson($root, $manifest['content']['lesson']);
        $quiz = $this->decodeJson($root, $manifest['content']['quiz']);
        if (! is_array($anatomy['structures'] ?? null) || ! is_array($lesson['steps'] ?? null) || ! is_array($quiz['questions'] ?? null) || ($quiz['formative'] ?? null) !== true) {
            $this->fail('content', 'Anatomy structures, lesson steps, and a formative quiz are required.');
        }

        return $manifest;
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $root, string $relativePath): array
    {
        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (! is_file($path)) {
            $this->fail('content', "A referenced JSON file is missing: {$relativePath}");
        }

        try {
            $value = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->fail('content', "A JSON file is malformed: {$relativePath}");
        }
        if (! is_array($value)) {
            $this->fail('content', "A JSON file must contain an object: {$relativePath}");
        }

        return $value;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
