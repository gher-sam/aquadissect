<?php

return [
    'version' => env('APP_VERSION', '1.0.0'),
    'auto_migrate' => env('APP_AUTO_MIGRATE', env('APP_ENV', 'production') === 'local'),
];
