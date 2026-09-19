<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'class_id', 'learning_module_id', 'learning_module_version_id', 'assigned_by', 'required', 'active',
    'assigned_at', 'starts_at', 'due_at',
])]
class ClassModuleAssignment extends Model
{
    use ReplicatesToDelayedClone;

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'active' => 'boolean',
            'assigned_at' => 'datetime',
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function learningClass(): BelongsTo
    {
        return $this->belongsTo(LearningClass::class, 'class_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LearningModuleVersion::class, 'learning_module_version_id');
    }
}
