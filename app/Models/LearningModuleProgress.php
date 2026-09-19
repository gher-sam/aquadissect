<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'class_id', 'learning_module_version_id', 'completed_step_ids', 'source', 'completed_at', 'synced_at',
])]
class LearningModuleProgress extends Model
{
    use ReplicatesToDelayedClone;

    protected $table = 'learning_module_progress';

    protected function casts(): array
    {
        return [
            'completed_step_ids' => 'array',
            'completed_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LearningModuleVersion::class, 'learning_module_version_id');
    }

    public function learningClass(): BelongsTo
    {
        return $this->belongsTo(LearningClass::class, 'class_id');
    }
}
