<?php

namespace App\Support;

class PortalPages
{
    public const PAGES = [
        'records' => ['record-management', 'admin'],
        'source-handover' => ['source-handover', 'admin'],
        'login' => ['login', null], 'register' => ['instructor-registration', null],
        'activate' => ['activate', null], 'about' => ['help', null], 'access' => ['account-roles', null],
        'home' => ['dashboard', 'student,instructor,program_head,admin'], 'library' => ['lessons', 'student,instructor,program_head,admin'],
        'safety' => ['safety-tools', 'student,instructor,program_head,admin'], 'classes' => ['class-sections', 'student,instructor,program_head,admin'],
        'roster' => ['people-sections', 'admin'], 'assignments' => ['activities', 'instructor'],
        'downloads' => ['offline-access', 'student,instructor,admin'], 'manager' => ['species-content', 'instructor,program_head,admin'],
        'prompt-studio' => ['media-prompts', 'instructor,admin'], 'calendar' => ['calendar', 'student,instructor,program_head,admin'],
        'notifications' => ['notifications', 'student,instructor,program_head,admin'], 'reports' => ['reports', 'student,instructor,program_head,admin'],
    ];

    public static function urls(): array
    {
        return collect(self::PAGES)->map(fn ($entry) => url('/'.$entry[0]))->all();
    }
}
