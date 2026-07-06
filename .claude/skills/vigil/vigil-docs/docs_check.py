#!/usr/bin/env python3
"""Deterministic doc-pattern linter for the Releezy docs doctrine.

Repo-agnostic: it discovers the layout instead of hardcoding service names, so it
works in any repo that follows the pattern (a `docs/` tree + thin `CLAUDE.md` stubs).
It enforces the OBJECTIVE half of the pattern; the judgment half (right folder?
relocated vs duplicated? did a code change need a doc change?) lives in SKILL.md and
is the human/agent's job.

HARD checks (exit 1 on any failure):
  1. No broken intra-repo markdown links anywhere in docs/ or in any CLAUDE.md stub.
  2. No nested CLAUDE.md under docs/ except the index docs/CLAUDE.md (deep indexes
     must be README.md so they aren't auto-loaded as instructions).
  3. No CLAUDE.md stub exceeds the hard line ceiling (a stub is pointers, not depth).

WARN checks (reported, never fail the run):
  - A stub showing depth-creep (code fences, or too many H2 sections).
  - A docs/ top-level folder the index doesn't mention.
  - The index missing the "reflects CURRENT state" doctrine line.
  - Living docs without a `> Related` block (summary count).
  - A sub-index README.md that has child folders it doesn't name (child-index drift).

Usage:
  python3 docs_check.py [repo_root]      # default: git toplevel, else CWD
  exit 0 = clean, exit 1 = hard failure(s)

  Pass an explicit path to certify a git worktree in place — e.g.
  `python3 docs_check.py .claude/worktrees/<branch>`. The worktree-skip only
  applies to worktrees nested *below* the scanned root, so a plain repo-root
  run still ignores them, while pointing the linter AT a worktree scans it.
"""
import os
import re
import subprocess
import sys

# --- config (portable; the only repo-shaped knobs) ---
EXCLUDE_DIR_PARTS = {"node_modules", "vendor", ".git"}
EXCLUDE_PATH_SUBSTR = (".claude/worktrees/",)
# CLAUDE.md files that are MASTER instructions, not service stubs (exempt from stub rules):
MASTER_CLAUDE = {".claude/CLAUDE.md", ".claude/CLAUDE.local.md"}
# CLAUDE.md under these prefixes are config/tooling, not service stubs (exempt):
STUB_EXEMPT_PREFIXES = (".claude/",)
STUB_HARD_MAX_LINES = 70   # a pointer-stub over this is regrowing into a doc
STUB_WARN_MAX_LINES = 45
STUB_WARN_MAX_H2 = 4       # header + Critical + Where-documented (+slack) is plenty
LINK_RE = re.compile(r"!?\[[^\]]*\]\(([^)\s]+)(?:\s+\"[^\"]*\")?\)")


def read_text(path):
    """Read a file fully, closing the handle deterministically. None on error."""
    try:
        with open(path, encoding="utf-8") as fh:
            return fh.read()
    except Exception:
        return None


def repo_root():
    if len(sys.argv) > 1:
        return os.path.abspath(sys.argv[1])
    try:
        out = subprocess.check_output(["git", "rev-parse", "--show-toplevel"],
                                      stderr=subprocess.DEVNULL)
        return out.decode().strip()
    except Exception:
        return os.getcwd()


def excluded(path, root=None):
    # Match the worktree-skip against the path RELATIVE to the scanned root, so
    # pointing the linter AT a worktree (`root` inside `.claude/worktrees/`)
    # scans it, while a repo-root run still skips worktrees nested below it.
    rel = (os.path.relpath(path, root) if root else path).replace("\\", "/")
    if any(s in rel for s in EXCLUDE_PATH_SUBSTR):
        return True
    parts = set(rel.split("/"))
    return bool(parts & EXCLUDE_DIR_PARTS)


def find_md(root, subpath=None):
    base = os.path.join(root, subpath) if subpath else root
    out = []
    for dirpath, dirnames, filenames in os.walk(base):
        dirnames[:] = [d for d in dirnames if d not in EXCLUDE_DIR_PARTS
                       and not excluded(os.path.join(dirpath, d), root)]
        for fn in filenames:
            if fn.endswith(".md"):
                full = os.path.join(dirpath, fn)
                if not excluded(full, root):
                    out.append(full)
    return out


def find_claude_mds(root):
    out = []
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = [d for d in dirnames if d not in EXCLUDE_DIR_PARTS
                       and not excluded(os.path.join(dirpath, d), root)]
        if "CLAUDE.md" in filenames:
            full = os.path.join(dirpath, "CLAUDE.md")
            if not excluded(full, root):
                out.append(full)
    return out


def check_links(root, files):
    """Return list of (relfile, link, missing_rel)."""
    broken = []
    for f in files:
        d = os.path.dirname(f)
        txt = read_text(f)
        if txt is None:
            continue
        for m in LINK_RE.finditer(txt):
            link = m.group(1).strip()
            if link.startswith(("http://", "https://", "mailto:", "#", "tel:")):
                continue
            path = link.split("#")[0]
            if not path:
                continue
            cand = (os.path.normpath(root + path) if path.startswith("/")
                    else os.path.normpath(os.path.join(d, path)))
            if not os.path.exists(cand):
                broken.append((os.path.relpath(f, root), link,
                               os.path.relpath(cand, root)))
    return broken


def main():
    root = repo_root()
    docs = os.path.join(root, "docs")
    hard, warn = [], []

    all_claude = find_claude_mds(root)
    docs_md = find_md(root, "docs") if os.path.isdir(docs) else []

    # files to link-check: every docs md + every CLAUDE.md (master + stubs) in-repo
    link_targets = sorted(set(docs_md) | set(all_claude))
    for relf, link, miss in check_links(root, link_targets):
        hard.append(f"[broken-link] {relf} -> [{link}] missing: {miss}")

    # classify CLAUDE.md
    index = os.path.join(docs, "CLAUDE.md")
    for cm in all_claude:
        rel = os.path.relpath(cm, root).replace("\\", "/")
        if rel in MASTER_CLAUDE or rel.startswith(STUB_EXEMPT_PREFIXES):
            continue
        if os.path.abspath(cm) == os.path.abspath(index):
            continue  # the docs index is allowed to be long
        # rogue nested CLAUDE.md inside docs/
        if rel.startswith("docs/"):
            hard.append(f"[rogue-claude] {rel} — nested CLAUDE.md under docs/ is "
                        f"auto-loaded as instructions; rename to README.md")
            continue
        # stub rules
        body = read_text(cm) or ""
        n = len(body.splitlines())
        if n > STUB_HARD_MAX_LINES:
            hard.append(f"[stub-bloat] {rel} — {n} lines (>{STUB_HARD_MAX_LINES}); a "
                        f"CLAUDE.md is a pointer-stub, depth belongs in docs/")
        elif n > STUB_WARN_MAX_LINES:
            warn.append(f"[stub-size] {rel} — {n} lines (>{STUB_WARN_MAX_LINES}); trim toward pointers")
        h2 = len(re.findall(r"(?m)^## ", body))
        fences = body.count("```")
        if n <= STUB_HARD_MAX_LINES and (h2 > STUB_WARN_MAX_H2 or fences // 2 > 1):
            warn.append(f"[stub-drift] {rel} — {h2} H2 sections / {fences//2} code blocks; "
                        f"depth-creep, move detail to docs/")

    # index sanity
    if os.path.isfile(index):
        idx = read_text(index) or ""
        if "CURRENT state" not in idx:
            warn.append("[index] docs/CLAUDE.md missing the 'reflects CURRENT state' doctrine line")
        if os.path.isdir(docs):
            for entry in sorted(os.listdir(docs)):
                p = os.path.join(docs, entry)
                if os.path.isdir(p) and entry not in EXCLUDE_DIR_PARTS:
                    if entry not in idx:
                        warn.append(f"[index] docs/{entry}/ not referenced in the index tree")
    else:
        hard.append("[index] docs/CLAUDE.md (the index) is missing")

    # > Related coverage (living docs only) — summary warn
    FROZEN_SEGMENTS = {"archive", "competitors", "reports", "decisions", "tasks"}
    living = [f for f in docs_md
              if not (FROZEN_SEGMENTS & set(os.path.relpath(f, docs).split(os.sep)))
              and os.path.basename(f) not in {"CLAUDE.md", "README.md"}]
    no_rel = []
    for f in living:
        body = read_text(f) or ""
        if "> **Related**" not in body and "> Related" not in body:
            no_rel.append(os.path.relpath(f, root))
    if no_rel:
        warn.append(f"[related] {len(no_rel)} living doc(s) lack a `> Related` block: "
                    + ", ".join(sorted(no_rel)[:8]) + (" …" if len(no_rel) > 8 else ""))

    # child-index coverage — a sub-index README.md should name its direct child folders (WARN)
    for f in docs_md:
        if os.path.basename(f) != "README.md":
            continue
        if FROZEN_SEGMENTS & set(os.path.relpath(f, docs).split(os.sep)):
            continue
        d = os.path.dirname(f)
        children = [e for e in sorted(os.listdir(d))
                    if os.path.isdir(os.path.join(d, e))
                    and e not in EXCLUDE_DIR_PARTS
                    and not excluded(os.path.join(d, e))]
        missing = [c for c in children if c not in (read_text(f) or "")]
        if missing:
            warn.append(f"[child-index] {os.path.relpath(f, root)} — sub-index doesn't "
                        f"name child folder(s): {', '.join(missing)}")

    # report
    print(f"docs-check @ {root}")
    print(f"  scanned {len(link_targets)} md files, {len(all_claude)} CLAUDE.md")
    if warn:
        print(f"\nWARN ({len(warn)}):")
        for w in warn:
            print(f"  - {w}")
    if hard:
        print(f"\nFAIL ({len(hard)}):")
        for h in hard:
            print(f"  - {h}")
        print("\nRESULT: FAIL")
        return 1
    print("\nRESULT: PASS — docs follow the pattern (0 hard violations)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
