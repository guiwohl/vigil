<?php

namespace App\Services;

use App\Models\Check;
use App\Models\Incident;
use App\Models\IncidentUpdate;
use App\Models\Monitor;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * The uptime engine: probes a monitor's URL, records the check, and drives the
 * incident state machine (failure-threshold auto-open, recovery auto-resolve).
 * Ported from the reference vigil-fastapi app/services/monitoring.py.
 */
class MonitoringService
{
    /**
     * @return Collection<int, Monitor>
     */
    public function dueMonitors(): Collection
    {
        return Monitor::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Monitor $monitor) => $monitor->isDue())
            ->values();
    }

    /**
     * @return array{up: bool, status_code: int|null, response_time_ms: int, error: string|null}
     */
    public function probe(string $url, string $method, int $timeout): array
    {
        $start = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->withOptions(['allow_redirects' => true])
                ->send($method, $url);

            $elapsed = (int) round((microtime(true) - $start) * 1000);
            $up = $response->status() < 400;

            return [
                'up' => $up,
                'status_code' => $response->status(),
                'response_time_ms' => $elapsed,
                'error' => $up ? null : 'HTTP '.$response->status(),
            ];
        } catch (\Throwable $exception) {
            $elapsed = (int) round((microtime(true) - $start) * 1000);

            return [
                'up' => false,
                'status_code' => null,
                'response_time_ms' => $elapsed,
                'error' => $exception->getMessage() ?: class_basename($exception),
            ];
        }
    }

    public function runCheck(Monitor $monitor): Check
    {
        $result = $this->probe($monitor->url, $monitor->method, $monitor->timeout_seconds);
        $now = now();

        return DB::transaction(function () use ($monitor, $result, $now) {
            $check = Check::create([
                'monitor_id' => $monitor->id,
                'tenant_id' => $monitor->tenant_id,
                'checked_at' => $now,
                'up' => $result['up'],
                'status_code' => $result['status_code'],
                'response_time_ms' => $result['response_time_ms'],
                'error' => $result['error'],
            ]);

            $monitor->last_checked_at = $now;

            if ($result['up']) {
                $this->handleUp($monitor, $now);
            } else {
                $this->handleDown($monitor, $now, $result['error']);
            }

            $monitor->save();

            return $check;
        });
    }

    private function handleDown(Monitor $monitor, CarbonInterface $now, ?string $error): void
    {
        $monitor->consecutive_failures++;

        if ($monitor->consecutive_failures < $monitor->failure_threshold) {
            return;
        }

        $monitor->status = Monitor::DOWN;

        if ($this->activeIncident($monitor) !== null) {
            return;
        }

        $incident = Incident::create([
            'tenant_id' => $monitor->tenant_id,
            'monitor_id' => $monitor->id,
            'title' => "{$monitor->name} is down",
            'status' => Incident::OPEN,
            'is_auto' => true,
            'started_at' => $now,
        ]);

        IncidentUpdate::create([
            'incident_id' => $incident->id,
            'tenant_id' => $monitor->tenant_id,
            'message' => 'Automated check failed ('.($error ?: 'no response').'). Investigating.',
            'status' => Incident::OPEN,
        ]);
    }

    private function handleUp(Monitor $monitor, CarbonInterface $now): void
    {
        $wasDown = $monitor->status === Monitor::DOWN;
        $monitor->consecutive_failures = 0;
        $monitor->status = Monitor::UP;

        if (! $wasDown) {
            return;
        }

        $incident = $this->activeIncident($monitor);

        if ($incident === null || ! $incident->is_auto) {
            return;
        }

        $incident->status = Incident::RESOLVED;
        $incident->resolved_at = $now;
        $incident->save();

        IncidentUpdate::create([
            'incident_id' => $incident->id,
            'tenant_id' => $monitor->tenant_id,
            'message' => 'Automated check recovered. Marking resolved.',
            'status' => Incident::RESOLVED,
        ]);
    }

    private function activeIncident(Monitor $monitor): ?Incident
    {
        return Incident::query()
            ->where('monitor_id', $monitor->id)
            ->whereIn('status', Incident::ACTIVE_STATES)
            ->orderByDesc('started_at')
            ->first();
    }
}
