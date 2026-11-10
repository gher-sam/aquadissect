<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class GuidedClipVideo
{
    public function prepare(UploadedFile $file, string $target): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! in_array($mime, ['video/mp4', 'video/webm'], true)) {
            $this->invalid('Choose a readable MP4 or WebM video.');
        }
        try {
            $before = $this->inspect($file->getRealPath());
            $duration = (float) ($before['format']['duration'] ?? 0);
            $streams = array_filter($before['streams'] ?? [], fn ($s) => ($s['codec_type'] ?? '') === 'video' && ! ($s['disposition']['attached_pic'] ?? false));
            $stream = array_values($streams)[0] ?? [];
            $streamDuration = (float) ($stream['duration'] ?? $duration);
            if ($duration <= 0 || $streamDuration <= 0 || max($duration, $streamDuration) > 10.000001 || count($streams) !== 1) {
                $this->invalid('Use one video track lasting no more than 10 seconds. Longer clips are not trimmed.');
            }
            if (($stream['width'] ?? 0) < 2 || ($stream['height'] ?? 0) < 2 || ($stream['width'] ?? 0) * ($stream['height'] ?? 0) > 33177600) {
                $this->invalid('Use a video no larger than 8K.');
            }
            $process = new Process([
                config('species-packages.ffmpeg_binary'), '-nostdin', '-y', '-v', 'error', '-xerror',
                '-protocol_whitelist', 'file,pipe', '-i', $file->getRealPath(), '-map', '0:v:0', '-map_metadata', '-1', '-map_chapters', '-1',
                '-an', '-sn', '-dn', '-vf', "scale=w='max(2,trunc(min(1280,min(iw*sar,720*dar))/2)*2)':h='max(2,trunc(min(720,min(ih,1280/dar))/2)*2)',setsar=1",
                '-r', '24', '-c:v', 'libx264', '-preset', 'medium', '-b:v', '1800k', '-maxrate', '2000k', '-bufsize', '4000k',
                '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-threads', '2', $target,
            ]);
            $process->setTimeout(120)->mustRun();
            $after = $this->inspect($target);
            $outputDuration = (float) ($after['format']['duration'] ?? 0);
            if (! is_file($target) || filesize($target) > 3 * 1024 * 1024 || $outputDuration <= 0 || $outputDuration > 10.000001 || abs($outputDuration - $duration) > 0.1) {
                $this->invalid('The playback copy could not retain the complete clip within the 10-second and 3 MiB limits.');
            }

            return ['durationSeconds' => $outputDuration, 'mimeType' => 'video/mp4', 'sizeBytes' => filesize($target), 'sha256' => hash_file('sha256', $target)];
        } catch (ValidationException $e) {
            @unlink($target);
            throw $e;
        } catch (\Throwable) {
            @unlink($target);
            $this->invalid('Video processing is unavailable or the file cannot be decoded. Ask the administrator to check FFmpeg and FFprobe, then retry.');
        }
    }

    private function inspect(string $path): array
    {
        $p = new Process([config('species-packages.ffprobe_binary'), '-v', 'error', '-protocol_whitelist', 'file,pipe', '-show_format', '-show_streams', '-of', 'json', $path]);
        $p->setTimeout(20)->mustRun();

        return json_decode($p->getOutput(), true, 32, JSON_THROW_ON_ERROR);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['asset' => $message]);
    }
}
