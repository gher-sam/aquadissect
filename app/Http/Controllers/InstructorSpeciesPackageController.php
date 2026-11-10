<?php

namespace App\Http\Controllers;

use App\Models\SpeciesPackage;
use App\Services\SpeciesCatalog;
use App\Services\SpeciesPackageImporter;
use App\Services\SpeciesPackageLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstructorSpeciesPackageController extends Controller
{
    public function store(Request $request, SpeciesPackageImporter $importer, SpeciesCatalog $catalog): JsonResponse
    {
        $validated = $request->validate(['package' => ['required', 'file', 'max:'.(int) (config('species-packages.max_archive_bytes') / 1024)]]);
        $package = $importer->import($validated['package'], $request->user());

        return response()->json(['package' => $catalog->packagePayload($package, $request->user())], 201);
    }

    public function submit(Request $request, SpeciesPackage $package, SpeciesPackageLifecycle $lifecycle, SpeciesCatalog $catalog): JsonResponse
    {
        $package = $lifecycle->submit($package, $request->user());

        return response()->json(['package' => $catalog->packagePayload($package, $request->user())]);
    }
}
