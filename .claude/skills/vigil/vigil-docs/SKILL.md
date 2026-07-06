---
name: vigil-docs
description: Use after touching `docs/` or ANY `CLAUDE.md`, before finishing or opening a PR — to certify the documentation still follows the pattern (lean index, thin hybrid `CLAUDE.md` stubs, 0 broken links). Triggered by "certify docs", "check docs", "do docs follow the pattern", or any change that adds/edits/removes docs or CLAUDE.md content.
---

# Vigil Docs — capture + pattern keeper

Two jobs, in order: **(1) capture** what this session learned into the committed docs, then
**(2) certify** the docs still follow the doctrine below. The certify half has a
**deterministic linter** (`docs_check.py`, beside this file) plus a **judgment checklist**
(you). Run both. Capture is yours alone — no script can tell what you learned.

---

## The pattern (the doctrine we enforce)

1. **`docs/` holds the depth. `CLAUDE.md` files are thin stubs.** Knowledge is *relocated*
   into `docs/`, never *duplicated* between a stub and a doc.
2. **The `docs/` tree** groups by category folder (`architecture/`, `api/`, `decisions/`,
   `runbooks/`), each with a one-line admission test. A doc that fits none → propose a
   folder, don't smuggle it in.
3. **The index (`docs/CLAUDE.md`) is lean and is the source of truth for structure** — it
   carries the "reflects CURRENT state" doctrine, the tree, a map-by-intent, and the folder
   admission tests. It does not restate doc content.
4. **Every living doc** opens with a one-line purpose and a `> **Related**` block linking
   adjacent docs. Frozen folders (`decisions/`) are exempt.
5. **"Docs reflect CURRENT state."** Describe the system as it is now — no "previously X /
   now Y / legacy." Migration narration belongs in commits/PRs.
6. **A `CLAUDE.md` stub is a hybrid:** one-line "what this is", an optional
   `## Critical — don't skip` block (≤5 footguns), and a `## Where it's documented` pointer
   list. Multiple code blocks or many H2s mean depth is creeping back — move it to `docs/`.
7. **Master instruction files are exempt** from stub rules (`.claude/CLAUDE.md`). A deep
   index under `docs/` must be `README.md`, not `CLAUDE.md`.

---

## How to capture + certify (run every time docs or a CLAUDE.md changed)

### 0. Capture — write down what you learned

Did this session surface something non-obvious a future agent would re-derive the hard way?
A gotcha, a constraint invisible in code, a why. If yes it gets **committed**: a
service-local footgun → the `CLAUDE.md` `Critical` block; anything deeper → the right
`docs/` file. Nothing learned? Skip — don't manufacture notes.

### 1. Mechanical — run the linter, get to 0 FAIL

```bash
python3 .claude/skills/vigil/vigil-docs/docs_check.py
```

It auto-discovers the layout (no hardcoded names) and HARD-fails on broken intra-repo
links, a nested `CLAUDE.md` under `docs/`, or a stub over the line ceiling. It WARNs on
stub depth-creep, a `docs/` folder the index forgot, a missing doctrine line, or living
docs lacking `> Related`. Fix every FAIL; treat WARNs as a to-do.

### 2. Judgment — the half the script can't see

- **Right folder?** Re-read the admission test for the folder you put a doc in.
- **Relocated, not duplicated?** A stub that moved knowledge into a doc must now *point*.
- **Did a code change need a doc change?** Cross-check the index's update-protocol.
- **Stub still thin?** Depth you added belongs in `docs/`.
- **New doc wired in?** One-line purpose + `> Related` (back-linked) + a map-by-intent row.

### 3. Re-run the linter — it must end on `RESULT: PASS` before you finish.

## This repo (the live anchor)

- **Index / source of truth for structure:** `docs/CLAUDE.md`.
- **Master instruction file (exempt from stub rules):** `.claude/CLAUDE.md`.
- **Docs categories:** `docs/architecture/` · `docs/api/` · `docs/decisions/` · `docs/runbooks/`.
- **Linter:** `.claude/skills/vigil/vigil-docs/docs_check.py` (stdlib only, no deps).
