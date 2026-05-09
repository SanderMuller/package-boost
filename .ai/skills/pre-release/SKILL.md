---
name: pre-release
description: "Pre-push / pre-release checklist. Local quality gates → docs audit → commit + push → watch CI → draft release notes once CI is green. Activate before: pushing release-bound commits, tagging a release, drafting release notes, or when user mentions: pre-release, pre-push, release checklist, ship, cut release, release notes."
---

# Pre-Release Checklist

End-to-end pre-release flow. Catches what the two-tier `backend-quality` skill skips (Rector drift, doc staleness, matrix breakage), pushes the work, watches CI, and only then drafts release notes. Each step gates the next — never skip ahead.

## When to Use This Skill

Activate when:
- About to push commits that will land in a release
- About to tag a release (`gh release create`)
- About to write or update release notes
- User says "ship it", "cut a release", "pre-push", "release checklist"
- A feature/fix is fully implemented and quality-gated

Do NOT use mid-development — this is a completion-level skill.

## Workflow

The order is load-bearing. Each phase gates the next:

```
1. Local quality (Rector → Pint → PHPStan → Pest)
2. Docs audit (README → .ai/ → references → UPGRADING.md if breaking)
3. Commit + push
4. Watch CI until matrix is green
5. Draft release notes (only after step 4 is green)
```

Always append `|| true` to verification commands so output is captured even on failure. Pass/fail is determined from the captured output, not the exit status alone.

---

## Phase 1: Local quality gates

Run in this order. Each must pass before the next.

### 1a. Rector

```bash
vendor/bin/rector process || true
```

Must report **0 files changed**. If Rector modifies files, review the diff, keep the changes, and re-run until clean.

### 1b. Pint

```bash
vendor/bin/pint --dirty --format agent || true
```

Must be clean. Re-run after Rector — Rector fixes can introduce style drift.

### 1c. PHPStan

```bash
vendor/bin/phpstan analyse --memory-limit=2G || true
```

Must show 0 errors. Fix real issues at the source — do not grow `phpstan-baseline.neon`. See `backend-quality` for baseline rules.

### 1d. Full test suite

```bash
vendor/bin/pest || true
```

Must show 0 failures.

**Optional cross-version sweep.** If the change touches anything version-sensitive (composer constraints, framework APIs that shifted across majors, deprecation guards), also run prefer-lowest/prefer-stable locally — CI's matrix can be narrower than what consumers actually install:

```bash
composer update --prefer-lowest --prefer-dist --no-interaction || true
vendor/bin/pest || true
composer update --prefer-stable --prefer-dist --no-interaction || true
vendor/bin/pest || true
```

For pure additions that use stable framework APIs (e.g. new flag on an existing command, no new composer constraints), skip the sweep — CI is sufficient.

---

## Phase 2: Documentation freshness audit

User-visible behavior changes can leave `README.md`, `.ai/` shipped docs, and reference files stale. **Rule:** add or edit docs only where they reflect a real change. Delete stale content aggressively.

### 2a. README

Scan `README.md` against the commits in this release (`git log <last-tag>..HEAD`). Update when:

- Public API signatures changed (method added, parameter added, behavior changed)
- Installation or usage instructions no longer match the supported Laravel/PHP matrix
- Examples reference removed or renamed classes / facades / commands
- JSON schema for `--format=json` changed — the shape contract in the CI drift check section must stay authoritative

If unsure: would a reader after the release see outdated advice? If yes, update.

The `readme` skill (`resources/boost/skills/readme/SKILL.md`) owns the canonical staleness-audit checklist — apply its **Audit pattern** section here.

### 2b. `.ai/` skills + guidelines

`.ai/skills/` and `.ai/guidelines/` are synced by `package-boost` (`vendor/bin/testbench package-boost:sync`) to per-agent skill dirs (listed in the README's *Agent coverage* table). Those generated files are gitignored — sync-command tests exercise the same paths.

For each edited-or-eligible doc, check:

- **Accuracy** — every command, path, rule name, and API example must work against current `main`.
- **Scope** — skills describe *when* to activate and *what steps* to run. Guidelines describe *conventions that persist*. Don't mix.
- **Non-bloat** — prefer tables and bullets over prose. One skill = one clear workflow.
- **Trigger words** — if a new workflow exists, make sure someone typing the natural-language ask can discover the skill via the frontmatter `description`.

If any `.ai/` file changed, re-sync to verify generation still works:

```bash
vendor/bin/testbench package-boost:sync || true
```

### 2c. References review

Scan `resources/boost/skills/{readme,release-notes,upgrading}/references/*.md` against current Laravel / Filament / Livewire / Nova majors. Stale framework-version notes propagate to every consumer.

### 2d. UPGRADING.md (only if breaking)

If this release has breaking changes (API removed, minimum PHP/Laravel raised, removed features, behavior contract changed):

- Author / update `UPGRADING.md` via the `upgrading` skill.
- Each `## Breaking changes` bullet in the release body (drafted later in Phase 5) must end with `[UPGRADING.md#anchor](UPGRADING.md#anchor)` resolving to a matching anchor.

Skip 2d for additive minor releases and bug-fix patches.

---

## Phase 3: Commit + push

Only enter this phase after Phases 1 and 2 are clean.

```bash
git status --short
git diff --stat HEAD
git log -5 --oneline
```

Stage explicitly (avoid `git add -A` — it can sweep in `RELEASE_NOTES_v*.md` drafts you haven't written yet, generated agent files that are intentionally gitignored, etc.):

```bash
git add <specific files>
git status --short
```

Commit with a sentence-case imperative subject and a body explaining *why* (matches this repo's history pattern; see `release-automation` guideline):

```bash
git commit -m "$(cat <<'EOF'
<Subject line — sentence case, imperative, no period>

<One-paragraph body explaining the why and any non-obvious behavior shifts>
EOF
)"
```

Push:

```bash
git push origin main
```

If the push is rejected because remote is ahead (commonly the auto-CHANGELOG commit from the prior release):

```bash
git pull --rebase origin main
git push origin main
```

---

## Phase 4: Watch CI

Trigger the workflows and wait for **every** check to be green before drafting release notes. The local pass in Phase 1 is necessary but not sufficient — CI runs the full matrix on clean runners.

```bash
gh run list --branch main --limit 5
```

The most recent runs should belong to the just-pushed commit. Each workflow appears as its own row.

Watch them complete. Two options:

```bash
# Option A: blocking watch (one workflow at a time)
gh run watch --exit-status

# Option B: poll every 30s until all complete
while gh run list --branch main --limit 8 --json status -q '.[].status' | grep -q -E 'queued|in_progress'; do
  sleep 30
done
gh run list --branch main --limit 8
```

When all rows show `completed	success`, Phase 4 is green. If any row shows `failure`, drill in:

```bash
gh run view <run-id> --log-failed
```

Fix the underlying issue, restart from Phase 1 (do not skip — a CI failure may surface something local checks missed).

**Matrix shape varies per repo.** Check `.github/workflows/run-tests.yml` for the actual axes — don't assume the matrix runs `prefer-lowest` if it doesn't. If consumers install on a constraint shape your matrix doesn't cover, run the optional cross-version sweep in Phase 1d.

---

## Phase 5: Draft release notes (only after Phase 4 is green)

Do not enter Phase 5 until **every** Phase 4 row shows `completed	success`. Drafting before CI green wastes work if a failing matrix cell forces a behavior fix that changes the user-facing description.

Use the `release-notes` skill for the body shape. Save to `RELEASE_NOTES_v<version>.md` (gitignored — see `.gitignore`). Paste the file contents as the GitHub release body on `gh release create`.

`CHANGELOG.md` is prepended with the release body automatically by `.github/workflows/update-changelog.yml` on release publish (`stefanzweifel/changelog-updater-action`). Do **not** edit `CHANGELOG.md` manually as part of the release PR. See the `release-automation` guideline.

---

## Quick reference

| Phase | Step | Command / artifact | Pass criteria |
|-------|------|--------------------|---------------|
| 1a | Rector | `vendor/bin/rector process \|\| true` | 0 files changed |
| 1b | Pint | `vendor/bin/pint --dirty --format agent \|\| true` | clean |
| 1c | PHPStan | `vendor/bin/phpstan analyse --memory-limit=2G \|\| true` | 0 errors |
| 1d | Pest | `vendor/bin/pest \|\| true` | 0 failures |
| 2a | README | apply `readme` skill's Audit pattern | no stale claims |
| 2b | `.ai/` | `vendor/bin/testbench package-boost:sync \|\| true` (only if `.ai/` touched) | `.ai/` ↔ generated in sync |
| 2c | References | scan `resources/boost/skills/*/references/*.md` | majors current |
| 2d | UPGRADING.md | (breaking only) `upgrading` skill | guide updated, anchors resolve |
| 3  | Commit + push | `git push origin main` | remote accepts push |
| 4  | Watch CI | `gh run list --branch main --limit 8` | every row `completed success` |
| 5  | Release notes | `RELEASE_NOTES_v<version>.md` via `release-notes` skill | drafted, ready to paste into `gh release create` |

## Important

- Run every phase, in order. Do not enter the next phase until the current one is clean.
- Do not push (Phase 3) if any Phase 1 or 2 check fails. Fix, then restart from 1a.
- Do not draft release notes (Phase 5) until **every** CI workflow row is green (Phase 4). Drafting before CI green is the most common scope-creep mistake — the description gets written, then CI fails, then the description has to be rewritten.
- Phase 2a and 2b are the most common source of silent drift — README and shipped skills are read by downstream users. Delete stale content before adding new.
- If a CI failure (Phase 4) produces a fix, that fix is itself a code change — restart from Phase 1a, not from Phase 4.
