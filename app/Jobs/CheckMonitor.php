<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\MonitoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckMonitor implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Monitor $monitor) {}

    public function handle(MonitoringService $monitoring): void
    {
        $monitoring->runCheck($this->monitor->fresh() ?? $this->monitor);
    }
}
