<?php

return [
    // Temporary presentation scope. Empty list restores all retained species.
    'visible_slugs' => ['rabbitfish'],
    'disk' => env('SPECIES_PACKAGES_DISK', 'local'),
    'root' => 'species-packages',
    'quarantine' => 'species-package-quarantine',
    'max_archive_bytes' => 250 * 1024 * 1024,
    'max_expanded_bytes' => 500 * 1024 * 1024,
    'ffmpeg_binary' => env('AQUADISSECT_FFMPEG_BINARY', 'ffmpeg'),
    'ffprobe_binary' => env('AQUADISSECT_FFPROBE_BINARY', 'ffprobe'),
    'max_files' => 1000,
    'allowed_extensions' => ['json', 'png', 'jpg', 'jpeg', 'webp', 'glb', 'mp4', 'webm'],
    'allowed_video_mime_types' => ['video/mp4', 'video/webm'],
    'max_video_bytes' => 80 * 1024 * 1024,
    'required_files' => [
        'manifest.json',
        'content/anatomy.json',
        'content/lesson.json',
        'content/quiz.json',
    ],
    'activity_modes' => ['virtual_dissection', 'anatomy_only', 'conservation'],
    'viewer_profiles' => ['layered-2d', 'layered-2d+3d'],
    'package_statuses' => ['draft', 'review', 'published', 'archived'],
];
