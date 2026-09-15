<?php

namespace App\Console\Commands;

use App\Models\CloneOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CloneStatus extends Command
{
    protected $signature = 'clone:status';

    protected $description = 'Show delayed SQLite clone configuration, queue health, and row counts';

    public function handle(): int
    {
        $pending = CloneOutbox::query()->whereNull('applied_at');
        $eligible = (clone $pending)->where('available_at', '<=', now())->count();
        $failed = (clone $pending)->where('attempts', '>', 0)->count();
        $next = (clone $pending)->min('available_at');
        $lastApplied = CloneOutbox::query()->whereNotNull('applied_at')->max('applied_at');

        $this->table(['Setting', 'Value'], [
            ['Enabled', config('delayed-clone.enabled') ? 'yes' : 'no'],
            ['Primary', DB::getDefaultConnection()],
            ['Clone', config('delayed-clone.connection')],
            ['Delay', config('delayed-clone.delay_hours').' hours'],
            ['Pending changes', $pending->count()],
            ['Eligible now', $eligible],
            ['Failed changes', $failed],
            ['Next eligible', $next ?? 'none'],
            ['Last applied', $lastApplied ?? 'none'],
        ]);

        $primary = DB::connection();
        $clone = DB::connection(config('delayed-clone.connection'));
        $rows = [];

        foreach (config('delayed-clone.tables') as $table => $keyName) {
            $rows[] = [$table, $primary->table($table)->count(), $clone->table($table)->count()];
        }

        $this->table(['Table', 'MySQL rows', 'SQLite rows'], $rows);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
