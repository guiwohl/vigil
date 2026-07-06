<?php

namespace App\Console\Commands;

use App\Jobs\CheckMonitor;
use App\Services\MonitoringService;
use Illuminate\Console\Command;

class DispatchDueChecks extends Command
{
    protected $signature = 'monitors:tick';

    protected $description = 'Dispatch a check job for every active monitor that is due';

    public function handle(MonitoringService $monitoring): int
    {
        $due = $monitoring->dueMonitors();

        foreach ($due as $monitor) {
            CheckMonitor::dispatch($monitor);
        }

        $this->info("Dispatched {$due->count()} check(s).");

        return self::SUCCESS;
    }
}
