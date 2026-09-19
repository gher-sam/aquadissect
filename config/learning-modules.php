<?php

return [
    'disk' => env('LEARNING_MODULES_DISK', 'local'),
    'root' => 'learning-modules',
    'max_image_bytes' => 12 * 1024 * 1024,
    'max_video_bytes' => 80 * 1024 * 1024,
    'ffmpeg_binary' => env('AQUADISSECT_FFMPEG_BINARY', 'ffmpeg'),
    'statuses' => ['draft', 'review', 'published', 'archived'],
];
