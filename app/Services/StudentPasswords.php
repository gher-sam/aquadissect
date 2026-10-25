<?php

namespace App\Services;

use App\Models\AccountActivationToken;
use App\Models\LearningClass;
use App\Models\RosterEvent;
use App\Models\StudentSetupChallenge;
use App\Models\StudentSetupCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentPasswords
{
    public const REASONS = ['forgot_password', 'student_request', 'first_sign_in_support'];

    public function authorize(User $actor, User $student, ?int $classId = null): void
    {
        abort_unless($actor->isActive() && $student->role === User::ROLE_STUDENT, 403);
        if ($actor->role === User::ROLE_ADMIN) {
            return;
        }
        abort_unless($actor->role === User::ROLE_INSTRUCTOR && $classId && LearningClass::whereKey($classId)->where('status', 'active')
            ->whereHas('course', fn ($q) => $q->where('code', 'MB-304L'))
            ->whereHas('instructors', fn ($q) => $q->where('user_id', $actor->id))
            ->whereHas('enrollments', fn ($q) => $q->where('user_id', $student->id)->where('status', 'active'))->exists(), 403);
    }

    public function initialize(User $student, User $actor, ?int $classId = null): bool
    {
        return DB::transaction(function () use ($student, $actor, $classId) {
            $student = User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $student, $classId);
            if ($student->status !== User::STATUS_PENDING || $student->must_change_password || $student->password_changed_at || ! $student->identity_number) {
                return false;
            }
            $this->temporary($student);
            $this->audit($student, $actor, 'student_initial_password_set', $classId);

            return true;
        });
    }

    public function reset(User $student, User $actor, string $reason, ?int $classId = null): void
    {
        abort_unless(config('student-setup.initial_password_enabled'), 503, 'Student password resets are not enabled.');
        DB::transaction(function () use ($student, $actor, $reason, $classId) {
            $student = User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $student, $classId);
            if (! in_array($reason, self::REASONS, true) || ! $student->identity_number
                || ! in_array($student->status, [User::STATUS_ACTIVE, User::STATUS_PENDING], true)) {
                throw ValidationException::withMessages(['student' => 'This student account cannot be reset. Refresh the roster.']);
            }
            $this->temporary($student);
            $this->audit($student, $actor, 'student_password_reset', $classId, ['reason' => $reason]);
        });
    }

    private function temporary(User $student): void
    {
        $student->forceFill(['password' => $student->identity_number, 'must_change_password' => true,
            'password_revision' => $student->password_revision + 1, 'remember_token' => Str::random(60)])->save();
        StudentSetupChallenge::where('user_id', $student->id)->delete();
        StudentSetupCode::where('user_id', $student->id)->delete();
        AccountActivationToken::where('user_id', $student->id)->get()->each->delete();
    }

    public function change(User $student, int $revision, string $password): User
    {
        return DB::transaction(function () use ($student, $revision, $password) {
            $student = User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            if ($student->role !== User::ROLE_STUDENT || ! $student->must_change_password || $revision !== $student->password_revision
                || ! in_array($student->status, [User::STATUS_PENDING, User::STATUS_ACTIVE], true)) {
                throw ValidationException::withMessages(['password' => 'The account changed. Sign in again before choosing your password.']);
            }
            if (strtoupper(trim($password)) === strtoupper((string) $student->identity_number) || Hash::check($password, $student->password)) {
                throw ValidationException::withMessages(['password' => 'Choose a new password that is different from your student ID and temporary password.']);
            }
            $pending = $student->status === User::STATUS_PENDING;
            $student->forceFill(['password' => $password, 'must_change_password' => false, 'password_changed_at' => now(),
                'password_revision' => $student->password_revision + 1, 'remember_token' => Str::random(60), 'status' => User::STATUS_ACTIVE])->save();
            $this->audit($student, $student, 'student_password_changed');
            if ($pending) {
                $this->audit($student, $student, 'student_activated', null, ['method' => 'student_id_initial_password']);
            }

            return $student;
        });
    }

    private function audit(User $student, User $actor, string $action, ?int $classId = null, array $details = []): void
    {
        RosterEvent::create(['actor_id' => $actor->id, 'user_id' => $student->id, 'class_id' => $classId,
            'action' => $action, 'details' => ['actor_role' => $actor->role, ...$details]]);
    }
}
