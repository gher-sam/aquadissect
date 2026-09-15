<?php

namespace App\Console\Commands;

use App\Services\CloneRefresher;
use Illuminate\Console\Command;

class RefreshClone extends Command
{
    protected $signature = 'clone:refresh {--yes : Replace clone tables without an interactive confirmation}';

    protected $description = 'Replace the SQLite domain tables with a consistent snapshot of MySQL';

    public function handle(CloneRefresher $refresher): int
    {
        if (! $this->option('yes') && ! $this->confirm('Replace the configured SQLite clone tables?')) {
            $this->components->info('Clone refresh cancelled.');

            return self::SUCCESS;
        }

        $counts = $refresher->refresh();

        $this->components->info('SQLite clone refreshed.');
        $this->table(
            ['Table', 'Rows copied'],
            collect($counts)->map(fn (int $count, string $table) => [$table, $count])->values()->all(),
        );

        return self::SUCCESS;
    }
}
