<?php

namespace App\Models\Concerns;

use App\Models\CloneOutbox;
use App\Services\DelayedCloneRecorder;
use Illuminate\Database\Eloquent\Model;

trait ReplicatesToDelayedClone
{
    public static function bootReplicatesToDelayedClone(): void
    {
        static::created(fn (Model $model) => app(DelayedCloneRecorder::class)
            ->record($model, CloneOutbox::OPERATION_CREATE));

        static::updated(fn (Model $model) => app(DelayedCloneRecorder::class)
            ->record($model, CloneOutbox::OPERATION_UPDATE));

        static::deleted(fn (Model $model) => app(DelayedCloneRecorder::class)
            ->record($model, CloneOutbox::OPERATION_DELETE));
    }
}
