<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'title', 'category', 'description'])]
class LearningModule extends Model
{
    use ReplicatesToDelayedClone;

    public function versions(): HasMany
    {
        return $this->hasMany(LearningModuleVersion::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ClassModuleAssignment::class);
    }
}
