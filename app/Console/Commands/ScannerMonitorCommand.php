<?php

namespace App\Console\Commands;

use App\Services\Scanner\SignalMonitor;
use Illuminate\Console\Command;

class ScannerMonitorCommand extends Command
{
    protected $signature = 'scanner:monitor {--dry-run : report without mutating}';
    protected $description = 'Monitor open scanner signals and close on TP/SL';

    public function handle(SignalMonitor $monitor): int
    {
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->info('Dry-run mode: monitoring state only.');
        }

        $result = $monitor->monitor(dryRun: $dryRun);
        $this->info(
            "Monitored {$result['processed']} open scanner signal(s), closed {$result['closed']}."
        );

        return 0;
    }
}