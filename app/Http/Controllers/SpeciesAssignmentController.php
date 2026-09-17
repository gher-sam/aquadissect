<?php

namespace App\Http\Controllers;

use App\Models\ClassInstructor;
use App\Models\LearningClass;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SpeciesAssignmentController extends Controller
{
    public function store(Request $request, LearningClass $class): JsonResponse
    {
        $this->authorizeOwner($request, $class);
        $validated = $request->validate([
            'speciesPackageId' => ['required', 'integer', 'exists:species_packages,id'],
            'startsAt' => ['nullable', 'date'],
            'dueAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
        ]);
        $package = SpeciesPackage::query()->published()->findOrFail($validated['speciesPackageId']);
        $assignment = SpeciesAssignment::query()->updateOrCreate(
            ['class_id' => $class->id, 'species_id' => $package->species_id],
            [
                'species_package_id' => $package->id,
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
                'starts_at' => $validated['startsAt'] ?? null,
                'due_at' => $validated['dueAt'] ?? null,
                'active' => true,
            ],
        );
        $package->events()->create([
            'user_id' => $request->user()->id,
            'event' => 'assigned',
            'metadata' => ['classId' => $class->id, 'explicitVersionSelection' => true],
        ]);

        return response()->json(['assignment' => $assignment->fresh(['species', 'package'])]);
    }

    public function destroy(Request $request, LearningClass $class): JsonResponse
    {
        $this->authorizeOwner($request, $class);
        $validated = $request->validate(['speciesId' => ['required', 'integer', 'exists:species,id']]);
        $assignment = SpeciesAssignment::query()
            ->where('class_id', $class->id)
            ->where('species_id', $validated['speciesId'])
            ->first();
        if (! $assignment) {
            throw ValidationException::withMessages(['speciesId' => 'That species is not assigned to this class.']);
        }
        $assignment->delete();

        return response()->json(['message' => 'Assignment removed.']);
    }

    private function authorizeOwner(Request $request, LearningClass $class): void
    {
        abort_unless(ClassInstructor::query()->where('class_id', $class->id)->where('user_id', $request->user()->id)->exists(), 403);
        abort_unless($class->status === 'active', 422, 'Archived sections cannot be changed.');
    }
}
