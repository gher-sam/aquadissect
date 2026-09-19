<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'class_id', 'user_id', 'learning_module_id', 'reason', 'granted_by', 'granted_at', 'revoked_by', 'revoked_at',
])]
class LearningModuleOverride extends Model
{
    use ReplicatesToDelayedClone;

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
