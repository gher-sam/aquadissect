<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name'])]
class Course extends Model
{
    use ReplicatesToDelayedClone;

    public function offerings(): HasMany
    {
        return $this->hasMany(LearningClass::class);
    }
}
