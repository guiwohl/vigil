# Local development

Run Vigil locally and prove the golden path (outage → auto-incident → recovery) end to end.

> **Related**: [`../architecture/overview.md`](../architecture/overview.md) ·
> [`../api/reference.md`](../api/reference.md)

## Prerequisites

Docker + Docker Compose. Everything else (PHP, Composer, Node) runs inside Sail — you do
not need them on the host.

## Start

```bash
cp .env.example .env      # then set the Stripe test keys if you want the billing demo
./vigil init              # build + start app/postgres/redis/horizon/scheduler, migrate, build assets
```

Open `http://localhost`. Sign up → you get a tenant with a public status page at
`http://localhost/status/{your-slug}`. Horizon is at `http://localhost/horizon`.

## Everyday commands

```bash
./vigil test             # PHPUnit suite (sqlite, no infra)
./vigil test --filter=Monitoring
./vigil lint             # Pint
./vigil artisan <...>    # any artisan command
./vigil tick             # dispatch one round of due checks by hand
./vigil logs horizon     # tail the queue worker
./vigil stop
```

## Golden-path demo (outage → auto-incident → recovery)

1. Sign up, then add a monitor pointing at a URL **you control** with `interval=5`,
   `failure_threshold=2` — e.g. a local receiver:
   ```bash
   # a killable target on the host, reachable from the app container:
   python3 -m http.server 9000
   # monitor URL: http://host.docker.internal:9000
   ```
2. The scheduler ticks every 5s → Horizon runs the check → the monitor goes `up`, the
   status page shows **All systems operational**.
3. **Kill the target** (Ctrl-C). Two consecutive failures cross the threshold: the monitor
   flips to `down`, an incident **auto-opens**, and within one tick + one poll (~10s) the
   public status page turns red **without a refresh** (it short-polls `/status/{slug}/data`).
4. **Bring the target back.** The next successful check **auto-resolves** the incident and
   the page returns to green.

Watch it happen in the queue: `./vigil logs horizon`.

## Billing demo (optional, needs your Stripe test keys)

Put `STRIPE_KEY` / `STRIPE_SECRET` / `STRIPE_PRICE_ID` / `STRIPE_WEBHOOK_SECRET` (all
test-mode) in `.env`. `/billing` → **Upgrade** opens a real Stripe Checkout Session; the
signature-verified webhook at `/stripe/webhook` flips the tenant's plan and monitor limit.
Automated tests don't need any keys — they sign payloads with a fake secret.
