<?php

namespace App\Http\Controllers;

use App\Models\SpeciesPackage;
use App\Services\SpeciesCatalog;
use App\Services\SpeciesPackageLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSpeciesPackageController extends Controller
{
    public function publish(Request $request, SpeciesPackage $package, SpeciesPackageLifecycle $lifecycle, SpeciesCatalog $catalog): JsonResponse
    {
        $result = $lifecycle->publish($package, $request->user());

        return response()->json([
            'package' => $catalog->packagePayload($result['package'], $request->user()),
            'upgradedAssignments' => $result['upgradedAssignments'],
            'pinnedAssignments' => $result['pinnedAssignments'],
        ]);
    }

    public function archive(Request $request, SpeciesPackage $package, SpeciesPackageLifecycle $lifecycle, SpeciesCatalog $catalog): JsonResponse
    {
        $archived = $lifecycle->archive($package, $request->user());

        return response()->json(['package' => $catalog->packagePayload($archived, $request->user())]);
    }

    public function activate(Request $request, SpeciesPackage $package, SpeciesPackageLifecycle $lifecycle, SpeciesCatalog $catalog): JsonResponse
    {
        $active = $lifecycle->activate($package, $request->user());

        return response()->json(['package' => $catalog->packagePayload($active, $request->user())]);
    }

    public function restore(Request $request, SpeciesPackage $package, SpeciesPackageLifecycle $lifecycle, SpeciesCatalog $catalog): JsonResponse
    {
        $restored = $lifecycle->restore($package, $request->user());

        return response()->json(['package' => $catalog->packagePayload($restored, $request->user())]);
    }
}
