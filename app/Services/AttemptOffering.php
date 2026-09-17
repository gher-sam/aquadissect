<?php

namespace App\Services;

use App\Models\LearningClass;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AttemptOffering
{
    public function resolve(User $user, int $packageId, ?int $requestedClassId): ?int
    {
        if ($user->role !== User::ROLE_STUDENT) {
            return null;
        }

        $ids = LearningClass::query()->where('status', 'active')
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $user->id)->where('status', 'active'))
            ->whereHas('assignments', fn ($query) => $query->where('species_package_id', $packageId)->where('active', true))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($requestedClassId !== null) {
            abort_unless(in_array($requestedClassId, $ids, true), 403, 'The selected section does not assign this package.');

            return $requestedClassId;
        }
        if (count($ids) === 1) {
            return $ids[0];
        }
        if ($ids === []) {
            abort(403, 'This package is not assigned to an active section.');
        }

        throw ValidationException::withMessages(['classId' => 'Choose the section for this attempt.']);
    }
}
