<?php

namespace App\Support;

use App\Models\LearningClass;
use App\Models\User;
use App\Services\SpeciesCatalog;

class PortalData
{
    public function __construct(private readonly SpeciesCatalog $catalog) {}

    /** @return array<string, mixed> */
    public function for(?User $user): array
    {
        if (! $user) {
            return $this->guest();
        }

        return [
            'user' => $user->portalProfile(),
            'lessonResult' => $user->latestLessonResult?->portalPayload(),
            'species' => $this->catalog->for($user),
            'classes' => $this->classesFor($user),
            'pendingUsers' => $user->isAdministrator()
                ? User::query()->pendingInstructors()->oldest()->get()->map->portalProfile()->values()
                : [],
        ];
    }

    /** @return array{user: null, lessonResult: null, pendingUsers: array<never>} */
    public function guest(): array
    {
        return [
            'user' => null,
            'lessonResult' => null,
            'species' => [],
            'classes' => [],
            'pendingUsers' => [],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function classesFor(User $user): array
    {
        $query = LearningClass::query()->with(['enrollments', 'instructors', 'assignments.species', 'assignments.package']);
        if ($user->role === User::ROLE_STUDENT) {
            $query->whereHas('enrollments', fn ($inner) => $inner->where('user_id', $user->id)->where('status', 'active'));
        } elseif ($user->role === User::ROLE_INSTRUCTOR) {
            $query->whereHas('instructors', fn ($inner) => $inner->where('user_id', $user->id));
        }

        return $query->orderBy('code')->get()->map(fn (LearningClass $class) => [
            'id' => $class->id,
            'code' => $class->code,
            'name' => $class->name,
            'studentCount' => $class->enrollments->where('status', 'active')->count(),
            'assignments' => $class->assignments->where('active', true)->map(fn ($assignment) => [
                'speciesId' => $assignment->species_id,
                'packageId' => $assignment->species_package_id,
                'commonName' => $assignment->species?->common_name,
                'version' => $assignment->package?->version,
            ])->values(),
        ])->values()->all();
    }
}
