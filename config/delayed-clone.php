<?php

return [
    'enabled' => env('CLONE_ENABLED', true),
    'connection' => env('CLONE_DB_CONNECTION', 'sqlite_clone'),
    'delay_hours' => (int) env('CLONE_DELAY_HOURS', 12),
    'batch_size' => (int) env('CLONE_BATCH_SIZE', 500),

    // Parent tables must appear before tables that reference them.
    'tables' => [
        'users' => 'id',
        'species' => 'id',
        'species_packages' => 'id',
        'species_package_assets' => 'id',
        'courses' => 'id',
        'classes' => 'id',
        'class_instructors' => 'id',
        'class_enrollments' => 'id',
        'species_assignments' => 'id',
        'species_package_events' => 'id',
        'lesson_results' => 'id',
        'account_activation_tokens' => 'id',
        'roster_events' => 'id',
    ],
];
