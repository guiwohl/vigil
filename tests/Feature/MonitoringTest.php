<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Monitor;
use App\Services\MonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MonitoringService
    {
        return app(MonitoringService::class);
    }

    public function test_a_single_failure_does_not_open_an_incident(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);
        $monitor = Monitor::factory()->create(['failure_threshold' => 2]);

        $this->service()->runCheck($monitor);
        $monitor->refresh();

        $this->assertSame(1, $monitor->consecutive_failures);
        $this->assertNotSame(Monitor::DOWN, $monitor->status);
        $this->assertSame(0, Incident::count());
        $this->assertSame(1, $monitor->checks()->count());
    }

    public function test_crossing_the_threshold_opens_an_incident_then_recovery_resolves_it(): void
    {
        Http::fakeSequence()
            ->pushStatus(500)
            ->pushStatus(500)
            ->pushStatus(200);

        $monitor = Monitor::factory()->create(['failure_threshold' => 2]);
        $service = $this->service();

        // First failure: below threshold, no incident.
        $service->runCheck($monitor);
        $monitor->refresh();
        $this->assertSame(0, Incident::count());

        // Second failure: threshold crossed, incident auto-opens.
        $service->runCheck($monitor);
        $monitor->refresh();

        $this->assertSame(Monitor::DOWN, $monitor->status);
        $this->assertSame(2, $monitor->consecutive_failures);

        $incident = Incident::first();
        $this->assertNotNull($incident);
        $this->assertTrue($incident->is_auto);
        $this->assertSame(Incident::OPEN, $incident->status);
        $this->assertContains($incident->status, Incident::ACTIVE_STATES);
        $this->assertSame(1, $incident->updates()->count());

        // Recovery: monitor comes back up, the auto incident resolves.
        $service->runCheck($monitor);
        $monitor->refresh();
        $incident->refresh();

        $this->assertSame(Monitor::UP, $monitor->status);
        $this->assertSame(0, $monitor->consecutive_failures);
        $this->assertSame(Incident::RESOLVED, $incident->status);
        $this->assertNotNull($incident->resolved_at);
        $this->assertSame(1, Incident::count(), 'recovery must not open a second incident');
    }

    public function test_repeated_failures_do_not_duplicate_the_incident(): void
    {
        Http::fake(['*' => Http::response('', 503)]);
        $monitor = Monitor::factory()->create(['failure_threshold' => 2]);
        $service = $this->service();

        $service->runCheck($monitor);
        $service->runCheck($monitor);
        $service->runCheck($monitor);
        $service->runCheck($monitor);

        $this->assertSame(1, Incident::count());
    }

    public function test_due_detection_respects_the_interval(): void
    {
        $never = Monitor::factory()->create(['last_checked_at' => null, 'interval_seconds' => 60]);
        $fresh = Monitor::factory()->create(['last_checked_at' => now(), 'interval_seconds' => 60]);
        $stale = Monitor::factory()->create(['last_checked_at' => now()->subSeconds(120), 'interval_seconds' => 60]);

        $this->assertTrue($never->isDue());
        $this->assertFalse($fresh->isDue());
        $this->assertTrue($stale->isDue());

        $dueIds = $this->service()->dueMonitors()->pluck('id');
        $this->assertContains($never->id, $dueIds);
        $this->assertContains($stale->id, $dueIds);
        $this->assertNotContains($fresh->id, $dueIds);
    }
}
