<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'status'])]
class LearningClass extends Model
{
    use ReplicatesToDelayedClone;

    protected $table = 'classes';

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
}
