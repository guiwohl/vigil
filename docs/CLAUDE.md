# Vigil docs — index

The `docs/` tree is the source of truth for how Vigil works. Docs reflect the
**CURRENT state** of the system — no "previously X / now Y", no migration narration (that
lives in commits/PRs). `CLAUDE.md` files elsewhere are thin pointer-stubs; depth lives here.

## Tree

```
docs/
├── decisions/      — ADRs (frozen once accepted). Start at 0001-architecture.md.
├── architecture/   — how the system fits together, right now.
├── api/            — the HTTP surface (routes, payloads, status codes).
└── runbooks/       — operational how-tos (local dev, the golden-path demo).
```

## Map by intent

- **Why is it built this way? the data model, the check/incident algorithm** →
  [`decisions/0001-architecture.md`](decisions/0001-architecture.md)
- **How do the pieces connect at runtime (scheduler → queue → state machine → page)** →
  [`architecture/overview.md`](architecture/overview.md)
- **What endpoints exist, what they return, what status codes** →
  [`api/reference.md`](api/reference.md)
- **Run it locally / prove the golden path (kill a target → incident → recover)** →
  [`runbooks/local-dev.md`](runbooks/local-dev.md)

## Folder admission tests

- `decisions/` — a decision with trade-offs we don't want to relitigate. Frozen; new
  decision → new numbered ADR, never edit an accepted one.
- `architecture/` — describes structure/flow as it is now. If it's a decision record it
  belongs in `decisions/`; if it's a step-by-step it belongs in `runbooks/`.
- `api/` — request/response contracts for the HTTP surface.
- `runbooks/` — a task an operator runs. Commands + expected output.

## Update protocol

A code change that alters the data model, a route, the check/incident algorithm, or the
billing flow must update the matching doc in the same change. Touched `docs/` or any
`CLAUDE.md`? Run the `vigil-docs` skill (`.claude/skills/vigil/vigil-docs/`) — it must
print `RESULT: PASS` before you finish.
