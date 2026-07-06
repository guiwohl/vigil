# Architecture overview

How Vigil's pieces connect at runtime: scheduler → queue → check → incident state machine
→ live public page.

> **Related**: [`../decisions/0001-architecture.md`](../decisions/0001-architecture.md) ·
> [`../api/reference.md`](../api/reference.md) ·
> [`../runbooks/local-dev.md`](../runbooks/local-dev.md)

## Containers (Sail)

- **laravel.test** — the app (HTTP, Inertia/React, `/horizon` dashboard). Host-exposed.
- **pgsql** — Postgres. **redis** — queue + Horizon backend + cache lock.
- **horizon** — `php artisan horizon`: processes the `CheckMonitor` jobs.
- **scheduler** — `php artisan schedule:work`: fires `monitors:tick` every 5s.

`./vigil init` builds and starts all five, migrates, and builds assets.

## The check loop

```
scheduler (schedule:work)
   └─ every 5s: artisan monitors:tick
        └─ MonitoringService::dueMonitors()  ── active + interval elapsed
             └─ CheckMonitor::dispatch($monitor)  ──▶ redis queue
                                                        └─ horizon worker
                                                             └─ MonitoringService::runCheck()
                                                                  ├─ HTTP probe (up = <400)
                                                                  ├─ insert checks row
                                                                  └─ incident state machine
```

Interval granularity is enforced per-monitor inside `dueMonitors()`, so the 5s tick is
just the resolution — a 60s monitor is still only checked once a minute.

## Where the logic lives

- `app/Services/MonitoringService.php` — probe, `runCheck`, and the incident state
  machine (threshold auto-open, recovery auto-resolve). The heart of the app.
- `app/Jobs/CheckMonitor.php` — the queued unit of work (one monitor, one check).
- `app/Console/Commands/DispatchDueChecks.php` — `monitors:tick`, the fan-out.
- `app/Models/Concerns/BelongsToTenant.php` — the row-level tenant global scope.
- `app/Http/Controllers/StatusController.php` — the public, un-scoped status snapshot.

## Multi-tenancy at runtime

`BelongsToTenant` filters every Eloquent query to `Auth::user()->tenant_id` when a user is
logged in. The scheduler, queue worker, and public status page run unauthenticated, so the
scope is inert there — they reach rows by explicit `monitor_id` / `tenant->relationship`,
never leaking across tenants. Cross-tenant reads by an authenticated user resolve to 404.

## Live status page

`GET /status/{slug}` renders the Inertia `status` page from a snapshot; the page then
short-polls `GET /status/{slug}/data` every 5s. One scheduler tick + one poll (≤10s)
surfaces an outage or a recovery with no manual refresh.
