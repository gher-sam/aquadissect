<?php
namespace App\Services;

use RuntimeException;
use ZipArchive;

class SourceRelease
{
    public static function allowed(string $path): bool
    {
        if (str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('~(^|/)(?:\.[^/]+|node_modules|vendor|storage|cache|uploads|backups)(/|$)~i', $path)) return false;
        if (in_array($path, ['artisan','composer.json','composer.lock','package.json','package-lock.json','vite.config.js','phpunit.xml','public/index.php','public/robots.txt','public/aquadissect-sw.js','public/manifest.webmanifest','HANDOVER.md'], true)) return true;
        return (bool) preg_match('~^(?:app/|config/|routes/|bootstrap/|database/(?:migrations|factories|seeders)/|resources/(?:js|css|views|species-packs|learning-modules|handover)/|tests/|public/(?:pwa-icons|species)/)[a-zA-Z0-9_./-]+\.(?:php|js|mjs|css|scss|json|svg|png|webp|jpg|mp4|glb|md)$~D', $path);
    }

    public function directory(): string { return storage_path('app/private/source-handover'); }

    public function manifest(): array
    {
        $path = $this->directory().'/current.json';
        abort_unless(is_file($path), 503, 'The source release is not prepared yet. Please try again after deployment completes.');
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        abort_unless(preg_match('/^[a-f0-9]{40}$/D', $data['revision'] ?? ''), 503);
        return $data;
    }

    public function archive(array $manifest): string
    {
        $path = $this->directory().'/'.$manifest['revision'].'.zip';
        abort_unless(is_file($path) && hash_equals($manifest['sha256'], hash_file('sha256', $path)), 503, 'Source release integrity check failed.');
        return $path;
    }

    public function read(array $manifest, string $path): string
    {
        abort_unless((self::allowed($path) || $path === '.env.example' || $path === 'public/.htaccess') && collect($manifest['files'])->contains('path', $path), 404);
        $zip = new ZipArchive;
        abort_unless($zip->open($this->archive($manifest)) === true, 503);
        try { $data = $zip->getFromName($path); abort_if($data === false, 404); return $data; }
        finally { $zip->close(); }
    }

    public function audit(int $userId, string $revision, string $target): void
    {
        $line = json_encode(['user_id'=>$userId,'revision'=>$revision,'target'=>$target,'requested_at'=>now()->toIso8601String()], JSON_THROW_ON_ERROR).PHP_EOL;
        if (file_put_contents($this->directory().'/downloads.jsonl', $line, FILE_APPEND | LOCK_EX) === false) throw new RuntimeException('Unable to record source download.');
    }
}
