# API reference

Vigil's HTTP surface. Authenticated routes are session-based (Inertia) and tenant-scoped;
form posts respond with Inertia redirects, error states with real status codes.

> **Related**: [`../architecture/overview.md`](../architecture/overview.md) ·
> [`../decisions/0001-architecture.md`](../decisions/0001-architecture.md)

## Auth (starter kit)

`GET/POST /login` · `GET/POST /register` · `POST /logout` · password-reset routes.
Registration also provisions the tenant (name `"<name>'s Team"`, unique slug, `free`
plan, default `monitor_limit`) and links the user to it.

## Monitors (auth, tenant-scoped)

| Method | Path | Notes |
| --- | --- | --- |
| POST | `/monitors` | Create. Body: `name`, `url`, `interval_seconds?`, `timeout_seconds?`, `failure_threshold?`. Past `monitor_limit` → **402**. |
| PATCH | `/monitors/{monitor}` | Update fields. Non-owned id → **404**. |
| DELETE | `/monitors/{monitor}` | Delete (cascades checks/incidents). Non-owned id → **404**. |

Monitor `status` is one of `up · down · paused · unknown`.

## Billing (auth)

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/billing` | Current plan, `monitor_limit`, whether Stripe is configured. |
| POST | `/billing/checkout` | Cashier Checkout Session redirect. Stripe keys absent → **503**. |

## Public (no auth)

| Method | Path | Returns |
| --- | --- | --- |
| GET | `/status/{slug}` | Inertia status page. Unknown slug → **404**. |
| GET | `/status/{slug}/data` | JSON snapshot (short-polled every 5s). |
| POST | `/status/{slug}/subscribe` | Body `email`. **201** `{ "ok": true }`. |

### Snapshot shape (`/status/{slug}/data`)

```json
{
  "tenant_name": "Acme",
  "slug": "acme-ab12cd",
  "overall": "operational | degraded | down",
  "monitors": [{ "id": 1, "name": "API", "status": "up", "last_checked_at": "..." }],
  "incidents": [{
    "id": 1, "title": "API is down", "status": "open",
    "started_at": "...", "resolved_at": null,
    "updates": [{ "message": "...", "status": "open", "created_at": "..." }]
  }]
}
```

`overall` = `down` if any monitor is down, else `degraded` if any incident is active,
else `operational`.

## Stripe webhook (no auth, signature-verified)

`POST /stripe/webhook` — Cashier's `VerifyWebhookSignature` middleware validates the
`Stripe-Signature` header against `STRIPE_WEBHOOK_SECRET`; a bad/missing signature → **403**.
Handled events: `checkout.session.completed` (upgrade tenant → `pro`, lift `monitor_limit`,
store `stripe_subscription_id`) and `customer.subscription.deleted` (downgrade → `free`).
