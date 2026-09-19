<?php

namespace App\Http\Controllers;

use App\Models\SpeciesPackage;
use App\Services\SpeciesCatalog;
use App\Services\SpeciesPackageLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffSpeciesPackageController extends Controller
{
    public function review(Request $request, SpeciesPackage $package, SpeciesPackageLifecycle $lifecycle, SpeciesCatalog $catalog): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'changes_requested'])],
            'notes' => ['required', 'string', 'min:10', 'max:3000'],
            'evidenceReference' => ['nullable', 'string', 'max:500'],
            'curriculumAlignmentConfirmed' => ['required', 'boolean'],
            'safetyReviewConfirmed' => ['required', 'boolean'],
        ]);

        $updated = $lifecycle->review(
            $package,
            $request->user(),
            $data['decision'],
            $data['notes'],
            $data['evidenceReference'] ?? null,
            $data['curriculumAlignmentConfirmed'],
            $data['safetyReviewConfirmed'],
        );

        return response()->json(['package' => $catalog->packagePayload($updated, $request->user())]);
    }
}
