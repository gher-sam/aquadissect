<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'code', 'name', 'status', 'academic_year', 'semester', 'section_code'])]
class LearningClass extends Model
{
    use ReplicatesToDelayedClone;

    protected $table = 'classes';

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function instructors(): HasMany
    {
        return $this->hasMany(ClassInstructor::class, 'class_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class, 'class_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SpeciesAssignment::class, 'class_id');
    }

    public function moduleAssignments(): HasMany
    {
        return $this->hasMany(ClassModuleAssignment::class, 'class_id');
    }
}
