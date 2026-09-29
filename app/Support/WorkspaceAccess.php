<?php

namespace App\Support;

use App\Models\LearningClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class WorkspaceAccess
{
    public static function classes(User $user): Builder
    {
        abort_unless($user->isActive(), 403);
        $query = LearningClass::query();
        if ($user->role === 'instructor') $query->whereHas('instructors', fn ($q) => $q->where('user_id', $user->id));
        elseif ($user->role === 'student') $query->whereHas('enrollments', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'));
        elseif (!in_array($user->role, ['admin', 'program_head'], true)) abort(403);
        return $query;
    }

    public static function manage(User $user, int $classId): void
    {
        abort_unless(in_array($user->role, ['admin', 'instructor']) && self::classes($user)->whereKey($classId)->where('status', 'active')->exists(), 403);
    }
}
