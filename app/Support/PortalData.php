<?php

namespace App\Support;

use App\Models\User;
use App\Services\SpeciesCatalog;

class PortalData
{
    public function __construct(private readonly SpeciesCatalog $catalog, private readonly ClassPresenter $classes) {}

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
            'classes' => $this->classes->for($user),
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
}
