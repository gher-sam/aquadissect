<?php

namespace App\Support;

class LearningMedia
{
    public const POLICY_VERSION = 2;

    public static function url(string $path, ?int $classId = null): string
    {
        return url($path).'?'.http_build_query(array_filter([
            'mediaPolicy' => self::POLICY_VERSION,
            'classId' => $classId,
        ], fn ($value) => $value !== null));
    }

    public static function headers(): array
    {
        // Offline copies are managed explicitly; the HTTP cache must not bypass account checks.
        return ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
    }
}
