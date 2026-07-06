# 0001 — Architecture

Status: Accepted · Date: 2026-07-06

Vigil is a multi-tenant status-page / uptime-monitoring SaaS. A tenant signs up, adds
monitors (a URL + a check interval), the Laravel scheduler dispatches a queued check job
on each monitor's interval, repeated failures auto-open an incident, and a public status
page at `/status/{slug}` reflects current state live. This ADR fixes the data model, the
routes, the queue/scheduler wiring, and the failure-threshold / auto-resolve algorithm
before any code — the same product as the reference FastAPI build, reimplemented
idiomatically on Laravel + Eloquent.

## Stack

- **Backend**: Laravel 12 (PHP 8.4+), Eloquent ORM + migrations, PHPUnit.
- **Frontend**: Inertia 2 + React 19 + Tailwind + shadcn/ui, on Laravel's official React
  starter kit (built-in login / register / password-reset).
- **DB**: Postgres (Sail). **Queue + scheduler**: Redis + Laravel Horizon.
- **Auth**: the starter kit's session auth. Registration provisions the tenant.
- **Billing**: Laravel Cashier (Stripe), Billable on the **Tenant**, flat tier by monitor
  count. Signature-verified webhook.
- **Container + CLI**: Laravel Sail; a `./vigil` bash wrapper for day-to-day commands.

## Multi-tenancy

Row-level. Every tenant-owned row carries a `tenant_id`; the `BelongsToTenant` trait adds
a global scope that filters every query to the authenticated user's tenant and stamps
`tenant_id` on insert. Unauthenticated system contexts (the queue worker, the scheduler,
the public status page) are intentionally un-scoped — they address tenants explicitly and
operate system-wide. No schema-per-tenant.

## Data model

- **tenants** — `id, name, slug (unique), plan, monitor_limit`, Cashier columns
  (`stripe_id, stripe_subscription_id, pm_type, pm_last_four, trial_ends_at`), timestamps.
  `slug` is the public status-page key. The tenant is the Cashier Billable.
- **users** — starter-kit users + `tenant_id` FK.
- **monitors** — `id, tenant_id, name, url, method, interval_seconds, timeout_seconds,
  failure_threshold, is_active, status (up|down|paused|unknown), consecutive_failures,
  last_checked_at`, timestamps.
- **checks** — ping history. `id, monitor_id, tenant_id, checked_at, up (bool),
  status_code, response_time_ms, error`.
- **incidents** — state machine. `id, tenant_id, monitor_id (nullable for manual),
  title, status (open|investigating|identified|monitoring|resolved), is_auto,
  started_at, resolved_at`.
- **incident_updates** — `id, incident_id, tenant_id, message, status`, timestamps.
- **subscribers** — status-page email subscribers. `id, tenant_id, email`.

The subscription is represented by `tenants.stripe_subscription_id` + `plan` +
`monitor_limit`; no separate table for a flat single-subscription tenant.

### Incident state machine

```
        auto-open (threshold hit)
unknown ─────────────────────────▶ open
                                     │  manual updates (tenant posts)
                                     ▼
                            investigating ─▶ identified ─▶ monitoring
                                     │            │            │
                                     └────────────┴────────────┘
                                                  │ auto-resolve (monitor recovers)
                                                  ▼      or manual resolve
                                              resolved
```

`open` and `investigating|identified|monitoring` are all "active". Only `resolved` is
terminal. Auto-opened incidents open as `open` and auto-resolve to `resolved`. Only
`is_auto` incidents are auto-resolved — manual incidents are tenant-owned.

## Scheduling + failure-threshold + auto-resolve algorithm

`routes/console.php` schedules `monitors:tick` `->everyFiveSeconds()` (sub-minute cadence,
so it runs under `schedule:work`, not a once-a-minute cron). Each tick:

1. `MonitoringService::dueMonitors()` selects active monitors where `last_checked_at`
   is null OR `last_checked_at + interval_seconds <= now`.
2. Dispatch one `CheckMonitor` job per due monitor (fan-out onto Horizon; concurrent).

`CheckMonitor::handle()` → `MonitoringService::runCheck($monitor)`:

1. HTTP request to `monitor.url` with `timeout_seconds`. `up = status < 400`.
2. Insert a `checks` row; set `last_checked_at = now`. (Whole step is one DB transaction.)
3. **Down path** (`up == false`): `consecutive_failures += 1`. When
   `consecutive_failures >= failure_threshold` and no active incident for this monitor
   exists: set `monitor.status = down`, open an `is_auto` incident (`open`) + first update.
4. **Up path** (`up == true`): if the monitor was `down` and an active `is_auto` incident
   exists, resolve it (`resolved`, `resolved_at = now`) + a resolution update; then
   `consecutive_failures = 0`, `monitor.status = up`.

Idempotent: the "active incident exists" guard prevents duplicate incidents; recovery
only resolves auto incidents.

## Routes

Auth (starter kit): `/login`, `/register` (provisions a tenant), `/logout`, password reset.

Authenticated, tenant-scoped:
- `GET /dashboard` — monitors + incidents + tenant snapshot (Inertia).
- `POST /monitors` (creating past `monitor_limit` → **402**), `PATCH /monitors/{monitor}`,
  `DELETE /monitors/{monitor}` — route-model binding is tenant-scoped, so another tenant's
  id resolves to **404**.
- `GET /billing`, `POST /billing/checkout` (Cashier; **503** if Stripe keys absent).

Public (no auth):
- `GET /status/{slug}` (Inertia page), `GET /status/{slug}/data` (JSON snapshot,
  short-polled every 5s), `POST /status/{slug}/subscribe`.

Webhook: `POST /stripe/webhook` — Cashier's `VerifyWebhookSignature` middleware rejects a
bad/missing signature with **403**; a valid `checkout.session.completed` upgrades the
tenant, `customer.subscription.deleted` downgrades it.

## Live status page

The Inertia `status` page renders from `GET /status/{slug}` then re-fetches
`GET /status/{slug}/data` every 5s client-side. Within one scheduler tick + one poll an
outage or recovery shows without a manual refresh. No websockets.

## Billing model

One flat Stripe subscription per tenant. `plan` = `free` (default, small `monitor_limit`)
or `pro` (lifts `monitor_limit`). Checkout creates a Stripe Checkout Session; the webhook
flips `plan` / `monitor_limit` / `stripe_subscription_id`. Tests sign payloads with a fake
webhook secret and assert both the state change and the tampered-signature rejection; the
live checkout demo needs the user's own Stripe test keys in `.env`.
