<?php

declare(strict_types=1);

$applicationBasePath = dirname(__DIR__);
$_ENV['APP_BASE_PATH'] = $applicationBasePath;
$_SERVER['APP_BASE_PATH'] = $applicationBasePath;

if (function_exists('putenv')) {
    putenv('APP_BASE_PATH='.$applicationBasePath);
}

require dirname(__DIR__).'/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\' => dirname(__DIR__).'/app/',
        'Database\\Factories\\' => dirname(__DIR__).'/database/factories/',
        'Database\\Seeders\\' => dirname(__DIR__).'/database/seeders/',
        'Tests\\' => dirname(__DIR__).'/tests/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $path = $directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
}, true, true);
