<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'class_id', 'learning_module_version_id', 'attempt_uuid', 'score', 'total', 'source',
    'completed_at', 'submitted_at', 'synced_at',
])]
class LearningModuleAttempt extends Model
{
    use ReplicatesToDelayedClone;

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LearningModuleVersion::class, 'learning_module_version_id');
    }
}
