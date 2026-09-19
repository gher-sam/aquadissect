<?php

namespace App\Services;

use App\Models\SpeciesPackage;
use App\Models\User;

class AttemptOffering
{
    public function __construct(private readonly LearningModuleGate $gate) {}

    public function resolve(User $user, int $packageId, ?int $requestedClassId): ?int
    {
        return $this->gate->resolveSpeciesClass($user, SpeciesPackage::query()->findOrFail($packageId), $requestedClassId);
    }
}
