<?php

namespace App\Http\Controllers;

use App\Models\ClassInstructor;
use App\Models\LearningClass;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = LearningClass::query()->with([
            'instructors',
            'enrollments',
            'assignments.species',
            'assignments.package',
        ])->orderBy('code');

        if ($user->role === User::ROLE_STUDENT) {
            $query->whereHas('enrollments', fn ($inner) => $inner->where('user_id', $user->id)->where('status', 'active'));
        } elseif ($user->role === User::ROLE_INSTRUCTOR) {
            $query->whereHas('instructors', fn ($inner) => $inner->where('user_id', $user->id));
        }

        return response()->json(['classes' => $query->get()->map(fn (LearningClass $class) => $this->payload($class))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/', Rule::unique('classes', 'code')],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $class = LearningClass::query()->create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'status' => 'active',
        ]);
        ClassInstructor::query()->create(['class_id' => $class->id, 'user_id' => $request->user()->id]);

        return response()->json(['class' => $this->payload($class->fresh(['instructors', 'enrollments', 'assignments']))], 201);
    }

    /** @return array<string, mixed> */
    private function payload(LearningClass $class): array
    {
        return [
            'id' => $class->id,
            'code' => $class->code,
            'name' => $class->name,
            'status' => $class->status,
            'studentCount' => $class->enrollments->where('status', 'active')->count(),
            'assignments' => $class->assignments->where('active', true)->map(fn ($assignment) => [
                'id' => $assignment->id,
                'speciesId' => $assignment->species_id,
                'speciesPackageId' => $assignment->species_package_id,
                'commonName' => $assignment->species?->common_name,
                'version' => $assignment->package?->version,
                'dueAt' => $assignment->due_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
