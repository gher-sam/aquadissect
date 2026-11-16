<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class PracticeCompletion extends Model
{
    use ReplicatesToDelayedClone;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Practice rewards cannot be overwritten.'));
        static::deleting(fn () => throw new LogicException('Practice rewards must be retained.'));
    }
}
