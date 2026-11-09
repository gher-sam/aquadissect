<?php

namespace App\Http\Controllers;

use App\Models\Species;
use App\Models\SpeciesPackage;
use App\Models\SpeciesPackageAsset;
use App\Services\SpeciesCatalog;
use App\Services\SpeciesPackageArchive;
use App\Services\SpeciesPackageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SpeciesController extends Controller
{
    public function index(Request $request, SpeciesCatalog $catalog): JsonResponse
    {
        return response()->json(['species' => $catalog->for($request->user())]);
    }

    public function manifest(Request $request, Species $species, SpeciesPackage $package, SpeciesPackageContent $content): JsonResponse
    {
        abort_unless($package->species_id === $species->id, 404);
        $classId = $request->integer('classId') ?: null;
        $content->authorize($request->user(), $package, $classId);

        return response()->json(['package' => $content->manifest($package->load('assets'), $classId)], 200, \App\Support\LearningMedia::headers());
    }

    public function asset(Request $request, SpeciesPackage $package, SpeciesPackageAsset $asset, SpeciesPackageContent $content): BinaryFileResponse
    {
        abort_unless($asset->species_package_id === $package->id, 404);
        $content->authorize($request->user(), $package, $request->integer('classId') ?: null);
        $path = $content->absolutePath($package, $asset->relative_path);

        return response()->file($path, array_merge(\App\Support\LearningMedia::headers(), [
            'Content-Type' => $asset->mime_type,
        ]));
    }

    public function cardPreview(Request $request, SpeciesPackage $package, SpeciesPackageContent $content): BinaryFileResponse
    {
        abort_unless($package->species->slug === 'rabbitfish' && $package->version === '1.1.0', 404);
        $content->authorize($request->user(), $package, $request->integer('classId') ?: null);

        $cutout = $request->query('format') === 'cutout';
        $angles = $request->query('format') === 'angles';

        return response()->file(resource_path($angles ? 'media/rabbitfish-angle-sheet.jpg' : ($cutout ? 'media/rabbitfish-card-cutout.webm' : 'media/rabbitfish-card-loop.mp4')), [
            'Content-Type' => $angles ? 'image/jpeg' : ($cutout ? 'video/webm' : 'video/mp4'),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(Request $request, SpeciesPackage $package, SpeciesPackageContent $content, SpeciesPackageArchive $archive): BinaryFileResponse
    {
        abort_unless(in_array($request->user()->role, ['instructor', 'admin'], true), 403);
        $content->authorize($request->user(), $package);

        return $archive->download($package->load('species'));
    }
}
