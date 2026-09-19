<?php

namespace App\Services;

use App\Models\LearningModuleAsset;
use App\Models\LearningModuleVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class LearningModuleAssetStore
{
    /** @param array<string, mixed> $metadata */
    public function store(LearningModuleVersion $version, UploadedFile $file, string $assetKey, array $metadata): LearningModuleAsset
    {
        if ($version->status === LearningModuleVersion::STATUS_PUBLISHED || $version->status === LearningModuleVersion::STATUS_ARCHIVED) {
            throw ValidationException::withMessages(['asset' => 'Create a new module version instead of changing published media.']);
        }

        [$temporary, $mime, $extension, $sanitization] = str_starts_with((string) $file->getMimeType(), 'image/')
            ? $this->sanitizeImage($file)
            : $this->sanitizeVideo($file);
        $relative = trim(config('learning-modules.root'), '/').'/'.$version->module->slug.'/'.$version->version.'/assets/'.Str::uuid().'.'.$extension;
        $contents = (string) file_get_contents($temporary);
        Storage::disk(config('learning-modules.disk'))->put($relative, $contents, ['visibility' => 'private']);
        @unlink($temporary);

        $asset = $version->assets()->updateOrCreate(['asset_key' => $assetKey], [
            'relative_path' => $relative,
            'mime_type' => $mime,
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'metadata' => [
                'aiGenerated' => (bool) ($metadata['aiGenerated'] ?? false),
                'source' => $metadata['source'],
                'licence' => $metadata['licence'],
                'caption' => $metadata['caption'],
                'reviewStatus' => 'awaiting_faculty_validation',
                'metadataSanitization' => $sanitization,
            ],
        ]);
        if ($version->status === LearningModuleVersion::STATUS_REVIEW) {
            $version->update(['status' => LearningModuleVersion::STATUS_DRAFT]);
        }

        return $asset;
    }

    /** @return array{string, string, string, string} */
    private function sanitizeImage(UploadedFile $file): array
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            throw ValidationException::withMessages(['asset' => 'The uploaded image could not be decoded.']);
        }
        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 320 || $height < 240 || $width > 6000 || $height > 6000) {
            imagedestroy($source);
            throw ValidationException::withMessages(['asset' => 'Use an image between 320×240 and 6000×6000 pixels.']);
        }
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        $target = tempnam(sys_get_temp_dir(), 'aqd-module-image-').'.webp';
        imagewebp($canvas, $target, 88);
        imagedestroy($source);
        imagedestroy($canvas);

        return [$target, 'image/webp', 'webp', 'server-reencoded'];
    }

    /** @return array{string, string, string, string} */
    private function sanitizeVideo(UploadedFile $file): array
    {
        $target = tempnam(sys_get_temp_dir(), 'aqd-module-video-').'.mp4';
        $process = new Process([
            config('learning-modules.ffmpeg_binary'), '-y', '-i', $file->getRealPath(), '-map_metadata', '-1',
            '-an', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-t', '15', $target,
        ]);
        try {
            $process->setTimeout(120)->mustRun();
        } catch (\Throwable) {
            @unlink($target);
            throw ValidationException::withMessages([
                'asset' => 'Video metadata sanitization is unavailable. Configure AQUADISSECT_FFMPEG_BINARY before uploading a clip.',
            ]);
        }

        return [$target, 'video/mp4', 'mp4', 'server-reencoded'];
    }
}
