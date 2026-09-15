<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class CloneRefresher
{
    /** @return array<string, int> */
    public function refresh(): array
    {
        $source = DB::connection();
        $clone = DB::connection(config('delayed-clone.connection'));
        $tables = config('delayed-clone.tables');
        $counts = [];

        $source->transaction(function () use ($source, $clone, $tables, &$counts): void {
            $outboxWatermark = (int) ($source->table('clone_outbox')->max('id') ?? 0);

            $this->disableForeignKeys($clone);

            try {
                $clone->transaction(function () use ($source, $clone, $tables, &$counts): void {
                    foreach (array_reverse(array_keys($tables)) as $table) {
                        $clone->table($table)->delete();
                    }

                    foreach ($tables as $table => $keyName) {
                        $counts[$table] = 0;
                        $source->table($table)->orderBy($keyName)->chunk(500, function ($rows) use ($clone, $table, &$counts): void {
                            $payload = $rows->map(fn (object $row) => (array) $row)->all();
                            if ($payload !== []) {
                                $clone->table($table)->insert($payload);
                                $counts[$table] += count($payload);
                            }
                        });
                    }
                });
            } finally {
                $this->enableForeignKeys($clone);
            }

            if ($outboxWatermark > 0) {
                $source->table('clone_outbox')
                    ->whereNull('applied_at')
                    ->where('id', '<=', $outboxWatermark)
                    ->update([
                        'applied_at' => now(),
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);
            }
        });

        return $counts;
    }

    private function disableForeignKeys(ConnectionInterface $connection): void
    {
        $connection->statement('PRAGMA foreign_keys = OFF');
    }

    private function enableForeignKeys(ConnectionInterface $connection): void
    {
        $connection->statement('PRAGMA foreign_keys = ON');
    }
}
