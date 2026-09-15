<?php

namespace App\Services;

use App\Models\SpeciesPackage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class SpeciesPackageArchive
{
    public function download(SpeciesPackage $package): BinaryFileResponse
    {
        $sourceRoot = storage_path('app/private/'.trim((string) $package->archive_path, '/'));
        if (! File::isDirectory($sourceRoot)) {
            abort(404, 'The package files are unavailable.');
        }

        $exportDirectory = storage_path('app/private/species-exports');
        File::ensureDirectoryExists($exportDirectory);
        $filename = "{$package->species->slug}-{$package->version}.aqpack";
        $archivePath = $exportDirectory.DIRECTORY_SEPARATOR.Str::uuid().'.aqpack';
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The package export could not be created.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($sourceRoot) + 1));
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }
        $zip->close();

        return response()->download($archivePath, $filename, [
            'Content-Type' => 'application/vnd.aquadissect.package+zip',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }
}
