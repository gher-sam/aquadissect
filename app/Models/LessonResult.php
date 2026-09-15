<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'species_package_id', 'lesson_key', 'attempt_uuid', 'package_version', 'score', 'total',
    'source', 'completed_at', 'submitted_at', 'synced_at',
])]
class LessonResult extends Model
{
    use HasFactory, ReplicatesToDelayedClone;

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SpeciesPackage::class, 'species_package_id');
    }

    /** @return array<string, mixed> */
    public function portalPayload(): array
    {
        return $this->only(['score', 'total', 'completed_at', 'lesson_key', 'package_version', 'source']);
    }
}
