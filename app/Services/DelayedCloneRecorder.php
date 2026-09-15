<?php

namespace App\Services;

use App\Models\CloneOutbox;
use Illuminate\Database\Eloquent\Model;

class DelayedCloneRecorder
{
    public function record(Model $model, string $operation): void
    {
        if (! config('delayed-clone.enabled')) {
            return;
        }

        CloneOutbox::query()->create([
            'table_name' => $model->getTable(),
            'key_name' => $model->getKeyName(),
            'record_key' => (string) $model->getKey(),
            'operation' => $operation,
            'payload' => $operation === CloneOutbox::OPERATION_DELETE
                ? null
                : $model->getAttributes(),
            'available_at' => now()->addHours(config('delayed-clone.delay_hours')),
        ]);
    }
}
