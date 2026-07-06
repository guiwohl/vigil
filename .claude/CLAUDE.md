# Vigil — AI Instructions

Multi-tenant status-page / uptime-monitoring SaaS. Laravel 12 + Inertia/React, Postgres,
Redis + Horizon. Tenants add monitors; the scheduler dispatches queued checks on each
monitor's interval; repeated failures auto-open an incident; a public `/status/{slug}` page
reflects it live.

## P0 — Non-negotiable

- **Test-first.** Write the failing PHPUnit test, watch it fail, implement, green, refactor.
  Real behavioral tests, no filler asserts.
- **Evidence before "done".** Run the verification command and read its output before
  claiming anything passes. No "should work".
- **KISS / SOLID / no bloat.** Ship the minimum that solves the task. No speculative knobs,
  wrapper layers, dead code, or defensive checks for impossible states.
- **No comments** except a rare load-bearing `// why`. Make the code self-explanatory.
- **Row-level multi-tenancy.** Tenant-owned models use the `BelongsToTenant` trait; never
  hand-write a query that could leak across tenants. Validate isolation in a test.
- **`./vigil` only.** Everything runs in Docker/Sail — use `./vigil <cmd>`, never raw
  docker compose. `./vigil help` lists commands.
- **Touched `docs/` or a `CLAUDE.md`?** Run the `vigil-docs` skill before finishing.

## Critical — don't skip

- The incident state machine + auto-resolve lives in `app/Services/MonitoringService.php`.
  Only `is_auto` incidents are auto-resolved; manual ones are tenant-owned.
- `BelongsToTenant`'s global scope is inert without an authenticated user — the scheduler,
  queue worker, and public status page are deliberately un-scoped and address tenants
  explicitly. Don't "fix" that into scoping them.
- Cashier's Billable is on the **Tenant**, not the User. Webhook signature verification is
  real (Cashier middleware); the webhook route is CSRF-exempt (`bootstrap/app.php`).
- Tests run on sqlite `:memory:` — keep migrations/models portable (no PG-only types).

## Where it's documented

- Architecture, data model, check/incident algorithm → `docs/decisions/0001-architecture.md`
- Runtime flow (scheduler → queue → state machine → page) → `docs/architecture/overview.md`
- HTTP surface → `docs/api/reference.md` · Run + golden-path demo → `docs/runbooks/local-dev.md`
- Full docs map → `docs/CLAUDE.md` (the index)
