# Vigil

Multi-tenant **status-page / uptime-monitoring** SaaS. Tenants add monitors (a URL + a
check interval); the Laravel scheduler dispatches a queued check on each monitor's
interval; repeated failures **auto-open an incident**; and every tenant gets a public,
unauthenticated status page at `/status/{slug}` that reflects current status and incident
history **live** (short-poll, no refresh).

Built as a portfolio piece to show real full-stack Laravel range: Inertia/React SPA,
row-level multi-tenancy via a global scope, a queued check pipeline on Horizon driven by
the scheduler, a real incident **state machine**, Stripe billing with a signature-verified
webhook, Docker/Sail, and a test-first PHPUnit suite — not tutorial CRUD.

## Architecture

```
                       ┌──────────── Sail (docker compose) ────────────┐
browser ──▶ laravel.test (Inertia/React + /horizon) ──▶ postgres        │
                 ▲                                                       │
   scheduler ── every 5s ─▶ monitors:tick ─▶ redis ─▶ horizon           │
     (schedule:work)                                    └─ CheckMonitor  │
                                                             └─ HTTP probe ─▶ monitored URL
                                                                └─ incident state machine ─┘
```

- **laravel.test** — Laravel 12, Inertia 2 + React 19 + Tailwind + shadcn, session auth.
- **scheduler** — `schedule:work`: fires `monitors:tick` every 5s (interval enforced per monitor).
- **horizon** — processes the `CheckMonitor` jobs; drives the incident state machine.
- **postgres** + **redis** — data + queue/Horizon backend.

The incident logic (failure-threshold open, auto-resolve on recovery) lives in
`app/Services/MonitoringService.php`. Details:
[`docs/decisions/0001-architecture.md`](docs/decisions/0001-architecture.md).

## Tech stack

PHP 8.4 · Laravel 12 · Inertia 2 · React 19 · Tailwind · shadcn/ui · Eloquent · PostgreSQL ·
Redis · Laravel Horizon · Laravel Cashier (Stripe) · Laravel Sail · PHPUnit · Pint.

## Quick start

```bash
cp .env.example .env      # add Stripe test keys for the billing demo (optional)
./vigil init              # build + start the stack, migrate, build assets
```

Open `http://localhost`. Sign up → add a monitor → watch it on your status page at
`http://localhost/status/{your-slug}`. Horizon dashboard: `http://localhost/horizon`.

### Golden-path demo (outage → auto-incident → recovery)

1. Add a monitor at a URL you control, `interval=5`, `failure_threshold=2`.
2. Kill the target → within ~10s an incident auto-opens and the status page goes red live.
3. Bring it back → the incident auto-resolves and the page returns to green.

Full walkthrough: [`docs/runbooks/local-dev.md`](docs/runbooks/local-dev.md).

## Billing (Stripe test mode)

Billing is real integration code (Checkout Session + signature-verified webhook), on a flat
subscription tier by monitor count, with Cashier's Billable on the **Tenant**. **The live
checkout demo needs your own Stripe test-mode keys** in `.env` (`STRIPE_KEY`,
`STRIPE_SECRET`, `STRIPE_PRICE_ID`, `STRIPE_WEBHOOK_SECRET`). Automated tests sign and
verify payloads with a fake secret, so CI needs no keys.

## Development

```bash
./vigil test     # PHPUnit suite (sqlite, no infra)
./vigil lint     # Pint
./vigil help     # all commands
```

CI (lint + test on push): [`tests.yml`](.github/workflows/tests.yml) ·
[`lint.yml`](.github/workflows/lint.yml).

## License

MIT.
