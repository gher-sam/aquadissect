<?php

namespace App\Support;

class PortalPages
{
    public const PAGES = [
        'source-handover' => ['source-handover', 'admin'],
        'login' => ['login', null], 'register' => ['instructor-registration', null],
        'activate' => ['activate', null], 'about' => ['help', null], 'access' => ['account-roles', null],
        'home' => ['dashboard', 'student,instructor,admin'], 'library' => ['lessons', 'student,instructor,admin'],
        'safety' => ['safety-tools', 'student,instructor,admin'], 'classes' => ['class-sections', 'student,instructor,admin'],
        'roster' => ['people-sections', 'admin'], 'assignments' => ['activities', 'instructor'],
        'downloads' => ['offline-access', 'student,instructor,admin'], 'manager' => ['species-content', 'instructor,admin'],
        'prompt-studio' => ['media-prompts', 'instructor,admin'], 'calendar' => ['calendar', 'student,instructor,admin'],
        'notifications' => ['notifications', 'student,instructor,admin'], 'reports' => ['reports', 'student,instructor,admin'],
    ];

    public static function urls(): array
    {
        return collect(self::PAGES)->map(fn ($entry) => url('/'.$entry[0]))->all();
    }
}
