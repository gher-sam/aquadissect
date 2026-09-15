<?php

namespace App\Services;

use App\Models\CloneOutbox;
use Illuminate\Support\Facades\DB;
use Throwable;

class DelayedCloneApplier
{
    /** @return array{eligible: int, applied: int, failed: int} */
    public function applyEligible(?int $limit = null): array
    {
        if (! config('delayed-clone.enabled')) {
            return ['eligible' => 0, 'applied' => 0, 'failed' => 0];
        }

        $limit ??= config('delayed-clone.batch_size');
        $events = CloneOutbox::query()->eligible()->oldest('id')->limit($limit)->get();
        $report = ['eligible' => $events->count(), 'applied' => 0, 'failed' => 0];

        foreach ($events as $event) {
            try {
                $this->apply($event);
                $event->forceFill([
                    'applied_at' => now(),
                    'attempts' => $event->attempts + 1,
                    'last_error' => null,
                ])->saveQuietly();
                $report['applied']++;
            } catch (Throwable $exception) {
                $event->forceFill([
                    'attempts' => $event->attempts + 1,
                    'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                ])->saveQuietly();
                $report['failed']++;
                report($exception);
            }
        }

        return $report;
    }

    private function apply(CloneOutbox $event): void
    {
        $connection = DB::connection(config('delayed-clone.connection'));

        $connection->transaction(function () use ($connection, $event): void {
            $table = $connection->table($event->table_name);

            if ($event->operation === CloneOutbox::OPERATION_DELETE) {
                $table->where($event->key_name, $event->record_key)->delete();

                return;
            }

            $table->updateOrInsert(
                [$event->key_name => $event->record_key],
                $event->payload ?? [],
            );
        });
    }
}
