<?php

namespace App\Support;

use App\Models\User;
use App\Services\LearningModuleCatalog;
use App\Services\SpeciesCatalog;
use App\Services\TutorialCatalog;

class PortalData
{
    public function __construct(
        private readonly SpeciesCatalog $catalog,
        private readonly ClassPresenter $classes,
        private readonly LearningModuleCatalog $learningModules,
    ) {}

    /** @return array<string, mixed> */
    public function for(?User $user): array
    {
        if ($user?->role === User::ROLE_STUDENT && $user->must_change_password) {
            return ['user' => $user->portalProfile(), 'passwordChangeRequired' => true, 'passwordChangeUrl' => route('password.change')];
        }
        if (! $user || ! $user->isActive()) {
            return $this->guest();
        }

        return [
            'user' => $user->portalProfile(),
            'accountAccess' => ['googleAvailable' => GoogleAccess::ready(), 'googleInstructorRegistration' => (bool) config('services.google.enabled')],
            'tutorials' => app(TutorialCatalog::class)->for($user),
            'capabilities' => [
                'programOversight' => in_array($user->role, ['program_head', 'admin'], true),
                'reviewContent' => in_array($user->role, ['instructor', 'program_head', 'admin'], true),
                'manageAccounts' => $user->isAdministrator(),
            ],
            'lessonResult' => $user->latestLessonResult?->portalPayload(),
            'species' => $this->catalog->for($user),
            'classes' => $this->classes->for($user),
            'learningModules' => $this->learningModules->for($user),
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
            'accountAccess' => ['googleAvailable' => GoogleAccess::ready(), 'googleInstructorRegistration' => (bool) config('services.google.enabled')],
            'lessonResult' => null,
            'species' => [],
            'classes' => [],
            'learningModules' => [],
            'pendingUsers' => [],
        ];
    }
}
