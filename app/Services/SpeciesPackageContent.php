<?php

namespace App\Services;

use App\Models\SpeciesPackage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SpeciesPackageContent
{
    public function __construct(private readonly LearningModuleGate $gate) {}

    public function authorize(User $user, SpeciesPackage $package, ?int $classId = null): void
    {
        if ($user->isAdministrator()) {
            return;
        }

        if ($user->role === User::ROLE_INSTRUCTOR) {
            if ($package->status === SpeciesPackage::STATUS_PUBLISHED || $package->imported_by === $user->id) {
                return;
            }
        }

        if ($user->role === User::ROLE_STUDENT && $package->status === SpeciesPackage::STATUS_PUBLISHED) {
            $this->gate->resolveSpeciesClass($user, $package, $classId);

            return;
        }

        throw new AccessDeniedHttpException('This species package is not available to this account.');
    }

    /** @return array<string, mixed> */
    public function manifest(SpeciesPackage $package, ?int $classId = null): array
    {
        $manifest = $package->manifest;
        $manifest['id'] = $package->id;
        $manifest['status'] = $package->status;
        $manifest['isCurrent'] = $package->is_current;
        $manifest['contentData'] = [
            'anatomy' => $this->readJson($package, $manifest['content']['anatomy']),
            'lesson' => $this->readJson($package, $manifest['content']['lesson']),
            'quiz' => $this->readJson($package, $manifest['content']['quiz']),
        ];
        $manifest['assetRecords'] = $package->assets->mapWithKeys(fn ($asset) => [
            $asset->asset_key => [
                'id' => $asset->id,
                'url' => url("/api/species-packages/{$package->id}/assets/{$asset->id}").($classId ? '?classId='.$classId : ''),
                'mimeType' => $asset->mime_type,
                'sizeBytes' => $asset->size_bytes,
                'sha256' => $asset->sha256,
            ],
        ])->all();

        return $manifest;
    }

    public function absolutePath(SpeciesPackage $package, string $relativePath): string
    {
        $path = trim((string) $package->archive_path, '/').'/'.ltrim($relativePath, '/');
        $disk = Storage::disk(config('species-packages.disk'));
        if (! $disk->exists($path)) {
            throw new NotFoundHttpException('The requested package file is unavailable.');
        }

        return $disk->path($path);
    }

    /** @return array<string, mixed> */
    private function readJson(SpeciesPackage $package, string $relativePath): array
    {
        $contents = file_get_contents($this->absolutePath($package, $relativePath));
        if ($contents === false) {
            throw new NotFoundHttpException('The requested package content is unavailable.');
        }

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }
}
