<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'learning_module_version_id', 'reviewer_id', 'reviewer_role', 'decision', 'notes', 'evidence_reference',
])]
class LearningModuleReview extends Model
{
    use ReplicatesToDelayedClone;

    public function version(): BelongsTo
    {
        return $this->belongsTo(LearningModuleVersion::class, 'learning_module_version_id');
    }
}
