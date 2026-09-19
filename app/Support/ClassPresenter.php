<?php

namespace App\Support;

use App\Models\LearningClass;
use App\Models\LearningModuleOverride;
use App\Models\LessonResult;
use App\Models\User;
use App\Services\LearningModuleGate;

class ClassPresenter
{
    public function __construct(private readonly LearningModuleGate $gate) {}

    /** @return array<int, array<string, mixed>> */
    public function for(User $user): array
    {
        $query = LearningClass::query()->with([
            'course', 'instructors.user', 'enrollments.user', 'assignments.species', 'assignments.package',
            'moduleAssignments.module', 'moduleAssignments.version',
        ]);
        if ($user->role === User::ROLE_STUDENT) {
            $query->whereHas('enrollments', fn ($inner) => $inner->where('user_id', $user->id)->where('status', 'active'));
        } elseif ($user->role === User::ROLE_INSTRUCTOR) {
            $query->whereHas('instructors', fn ($inner) => $inner->where('user_id', $user->id));
        }

        return $query->orderByDesc('academic_year')->orderBy('semester')->orderBy('section_code')
            ->get()->map(fn (LearningClass $class) => $this->one($class, $user))->all();
    }

    /** @return array<string, mixed> */
    public function one(LearningClass $class, User $viewer): array
    {
        $class->loadMissing([
            'course', 'instructors.user', 'enrollments.user', 'assignments.species', 'assignments.package',
            'moduleAssignments.module', 'moduleAssignments.version',
        ]);
        $students = [];
        if ($viewer->role !== User::ROLE_STUDENT) {
            $results = LessonResult::query()->where('class_id', $class->id)->orderByDesc('completed_at')->get()->groupBy('user_id');
            $students = $class->enrollments->filter(fn ($enrollment) => $enrollment->user?->role === User::ROLE_STUDENT)
                ->map(function ($enrollment) use ($results, $class): array {
                    $attempts = $results->get($enrollment->user_id, collect());

                    return [
                        'id' => $enrollment->user_id,
                        'name' => trim($enrollment->user->first_name.' '.$enrollment->user->last_name),
                        'email' => $enrollment->user->email,
                        'identityNumber' => $enrollment->user->identity_number,
                        'accountStatus' => $enrollment->user->status,
                        'enrollmentStatus' => $enrollment->status,
                        'attemptCount' => $attempts->count(),
                        'latestResult' => $attempts->first()?->portalPayload(),
                        'moduleStatus' => $this->gate->status($enrollment->user, $class->id),
                    ];
                })->values()->all();
        }

        return [
            'id' => $class->id,
            'courseCode' => $class->course?->code,
            'code' => $class->code,
            'name' => $class->name,
            'academicYear' => $class->academic_year,
            'semester' => $class->semester,
            'sectionCode' => $class->section_code,
            'status' => $class->status,
            'studentCount' => $class->enrollments->where('status', 'active')->count(),
            'instructors' => $class->instructors->map(fn ($item) => [
                'id' => $item->user_id,
                'name' => trim(($item->user?->first_name ?? '').' '.($item->user?->last_name ?? '')),
            ])->values()->all(),
            'students' => $students,
            'assignments' => $class->assignments->where('active', true)->map(fn ($assignment) => [
                'id' => $assignment->id,
                'speciesId' => $assignment->species_id,
                'speciesPackageId' => $assignment->species_package_id,
                'packageId' => $assignment->species_package_id,
                'commonName' => $assignment->species?->common_name,
                'version' => $assignment->package?->version,
                'dueAt' => $assignment->due_at?->toIso8601String(),
                'locked' => $viewer->role === User::ROLE_STUDENT && ! $this->gate->status($viewer, $class->id)['satisfied'],
            ])->values()->all(),
            'moduleAssignments' => $class->moduleAssignments->where('active', true)->map(fn ($assignment) => [
                'id' => $assignment->id,
                'moduleId' => $assignment->learning_module_id,
                'versionId' => $assignment->learning_module_version_id,
                'title' => $assignment->module?->title,
                'version' => $assignment->version?->version,
                'required' => $assignment->required,
                'dueAt' => $assignment->due_at?->toIso8601String(),
            ])->values()->all(),
            'moduleOverrides' => $viewer->role === User::ROLE_STUDENT ? [] : LearningModuleOverride::query()
                ->where('class_id', $class->id)->whereNull('revoked_at')->get()->map(fn ($override) => [
                    'id' => $override->id,
                    'moduleId' => $override->learning_module_id,
                    'studentId' => $override->user_id,
                    'reason' => $override->reason,
                    'grantedAt' => $override->granted_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }
}
