<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['first_name', 'last_name', 'email', 'identity_number', 'role', 'status', 'class_code', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use ReplicatesToDelayedClone;

    public const ROLE_STUDENT = 'student';

    public const ROLE_INSTRUCTOR = 'instructor';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PROGRAM_HEAD = 'program_head';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending';

    public const REGISTRABLE_ROLES = [self::ROLE_INSTRUCTOR];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function lessonResults(): HasMany
    {
        return $this->hasMany(LessonResult::class);
    }

    public function latestLessonResult(): HasOne
    {
        return $this->hasOne(LessonResult::class)->latestOfMany('completed_at');
    }

    public function learningModuleProgress(): HasMany
    {
        return $this->hasMany(LearningModuleProgress::class);
    }

    public function classEnrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    public function classInstructorAssignments(): HasMany
    {
        return $this->hasMany(ClassInstructor::class);
    }

    public function scopePendingInstructors(Builder $query): Builder
    {
        return $query
            ->where('role', self::ROLE_INSTRUCTOR)
            ->where('status', self::STATUS_PENDING);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** @return array<string, mixed> */
    public function portalProfile(): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'classCode' => $this->class_code,
        ];
    }
}
