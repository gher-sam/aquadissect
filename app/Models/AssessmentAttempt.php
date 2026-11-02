<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class AssessmentAttempt extends Model
{
    use ReplicatesToDelayedClone;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'submitted_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function ($attempt) {
            if ($attempt->isDirty(['assessment_study_id', 'user_id', 'phase', 'attempt_uuid', 'answers', 'score', 'total', 'submitted_at'])) {
                throw new LogicException('Submitted scores and answers cannot be overwritten.');
            }
        });
        static::deleting(fn () => throw new LogicException('Submitted attempts must be retained.'));
    }
}
