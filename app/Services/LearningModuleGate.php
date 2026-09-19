<?php

namespace App\Services;

use App\Models\ClassModuleAssignment;
use App\Models\LearningClass;
use App\Models\LearningModuleOverride;
use App\Models\LearningModuleProgress;
use App\Models\LearningModuleVersion;
use App\Models\SpeciesPackage;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LearningModuleGate
{
    /** @return array{satisfied: bool, required: int, completed: int, overridden: int} */
    public function status(User $user, int $classId): array
    {
        $assignments = ClassModuleAssignment::query()
            ->with('version')
            ->where('class_id', $classId)
            ->where('required', true)
            ->where('active', true)
            ->whereHas('version', fn ($query) => $query->whereIn('status', [
                LearningModuleVersion::STATUS_PUBLISHED,
                LearningModuleVersion::STATUS_ARCHIVED,
            ]))
            ->get();
        $completed = 0;
        $overridden = 0;

        foreach ($assignments as $assignment) {
            if ($this->hasOverride($user, $assignment)) {
                $overridden++;

                continue;
            }
            if (LearningModuleProgress::query()
                ->where('user_id', $user->id)
                ->where('class_id', $classId)
                ->whereHas('version', fn ($query) => $query
                    ->where('learning_module_id', $assignment->learning_module_id))
                ->whereNotNull('completed_at')->exists()) {
                $completed++;
            }
        }

        return [
            'satisfied' => $assignments->count() === $completed + $overridden,
            'required' => $assignments->count(),
            'completed' => $completed,
            'overridden' => $overridden,
        ];
    }

    public function hasOverride(User $user, ClassModuleAssignment $assignment): bool
    {
        return LearningModuleOverride::query()
            ->where('class_id', $assignment->class_id)
            ->where('learning_module_id', $assignment->learning_module_id)
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
            ->exists();
    }

    public function resolveSpeciesClass(User $user, SpeciesPackage $package, ?int $requestedClassId): ?int
    {
        if ($user->role !== User::ROLE_STUDENT) {
            return null;
        }

        $ids = LearningClass::query()->where('status', 'active')
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $user->id)->where('status', 'active'))
            ->whereHas('assignments', fn ($query) => $query->where('species_package_id', $package->id)->where('active', true))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($requestedClassId !== null) {
            abort_unless(in_array($requestedClassId, $ids, true), 403, 'The selected section does not assign this package.');
            $classId = $requestedClassId;
        } elseif (count($ids) === 1) {
            $classId = $ids[0];
        } elseif ($ids === []) {
            abort(403, 'This package is not assigned to an active section.');
        } else {
            throw ValidationException::withMessages(['classId' => 'Choose the section before opening this lesson.']);
        }

        if (! $this->status($user, $classId)['satisfied']) {
            throw ValidationException::withMessages([
                'prerequisite' => 'Complete the assigned Safety & Tools module, or ask your instructor for a documented exception.',
            ]);
        }

        return $classId;
    }
}
