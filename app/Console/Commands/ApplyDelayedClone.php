<?php

namespace App\Console\Commands;

use App\Services\DelayedCloneApplier;
use Illuminate\Console\Command;

class ApplyDelayedClone extends Command
{
    protected $signature = 'clone:apply {--limit= : Maximum eligible changes to apply}';

    protected $description = 'Apply MySQL changes that have completed the configured delay to SQLite';

    public function handle(DelayedCloneApplier $applier): int
    {
        $limit = $this->option('limit');
        $report = $applier->applyEligible($limit === null ? null : max(1, (int) $limit));

        $this->table(
            ['Eligible', 'Applied', 'Failed'],
            [[$report['eligible'], $report['applied'], $report['failed']]],
        );

        return $report['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
