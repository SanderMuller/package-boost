# Content-Drift Detection for Copied Skills

## Overview

Today, `SyncReporter::planSkillAction()` returns `'unchanged'` for any
non-symlink destination regardless of contents. On filesystems
without symlink support — occasionally Windows, some sandboxed CI —
the copy fallback in `SyncCommand::linkOrCopy()` runs, and subsequent
`package-boost:sync --check` invocations silently pass even when the
source skill has changed. Fix with a recursive hash compare between
source and dest trees, with a disambiguated hint so reporters can
tell a symlink retarget from a content drift.

---

## 1. Current State

**File:** `src/Console/SyncReporter.php:10`

```php
public static function planSkillAction(string $source, string $dest): array
{
    if (! is_link($dest) && ! file_exists($dest)) {
        return ['new', ''];
    }

    if (is_link($dest)) {
        // compare readlink to expected relative path
        return $actual === $expected ? ['unchanged', ''] : ['updated', $hint];
    }

    return ['unchanged', ''];  // ← unconditional, ignores content
}
```

The bug: if `$dest` is a plain directory (copy fallback), we always
report `unchanged`. Real drift becomes invisible to `--check`.

**File:** `src/Console/SyncCommand.php:237` (`linkOrCopy`)

The fallback runs when `@symlink()` fails — rare but real. Users in
that branch currently have zero drift detection beyond "was it
created at all?".

## 2. Proposed Changes

When `$dest` is an existing directory (not symlink, not missing),
hash-walk both `$source` and `$dest` and compare.

### Algorithm

1. Walk both trees with Symfony Finder (`->files()` recursive), skip
   files matching the dotfile filter (see below).
2. Build `{relativePath => md5}` maps for both. `md5_file` — this
   is change detection, not signing; md5 is ~3× faster than sha256
   on small files.
3. Diff:
   - Identical maps → `unchanged`.
   - Otherwise → `updated`, with a hint summarising the diff.

### Hint shape

Distinguish from the existing symlink-retarget hint via a prefix:

- Symlink retarget (unchanged): `(symlink → target)` — existing.
- Content drift: `(content: N files differ)` — new.
  - For mixed cases: `(content: N differ, M added, K removed)`.
  - Pure add / remove: `(content: M added)` / `(content: K removed)`.

Prefix `content:` prevents grep-based scripts from conflating the
two update causes.

### Dotfile policy

Skip files whose basename starts with `.` (e.g. `.DS_Store`,
`.gitattributes`, `.editorconfig`). Reasoning: skill trees should be
the shipped `SKILL.md` + referenced static resources; dotfiles are
usually filesystem / tooling detritus. A skill author with a
legitimate dotfile can add it to `resources/boost/skills/<name>/` and
it'll be copied — it just won't count toward drift. Acceptable
trade-off; revisit if a real consumer reports otherwise.

### Testing the copy-fallback path

Do not introduce a `PACKAGE_BOOST_DISABLE_SYMLINK` env hook in
production code. Test setup instead: in the `beforeEach`, manually
create the destination directory with mismatched contents — no
symlink, no sync-via-command — then run `--check` and assert the
expected drift. This exercises `planSkillAction`'s
directory-not-symlink branch without leaking test-only state into
SyncCommand.

## Implementation

- [ ] `SyncReporter::hashTree(string $dir): array<string, string>` —
  private, recursive, keyed by relative path. Skip dotfiles.
- [ ] `SyncReporter::planSkillAction` — when `$dest` is a directory
  (`is_dir && !is_link`), call `hashTree` on both sides, compare.
- [ ] `SyncReporter::renderContentHint(array $source, array $dest):
  string` — returns the `(content: ...)` string per the hint shape
  rules.
- [ ] Tests — identical trees (unchanged), one file differs
  (updated with `1 differ` hint), one file added, one file
  removed, mixed case, dotfile in source ignored.
- [ ] Test the copy-fallback path by pre-creating the destination
  directory with drifted contents; call `sync --check --skills`
  and assert exit code 1 plus the correct hint.
- [ ] Benchmark: run `sync` against a fixture with 25 skills, each
  ~2KB SKILL.md. Total walk must stay under 50ms warm-filesystem
  on a macOS SSD. Not a CI gate; just a smoke check.
- [ ] Document migration impact: consumers with copy-fallback skill
  dirs will see `~` on first `--check` after upgrade if their
  content had actually drifted. Expected — surfaces latent
  drift.
- [ ] Prune the entry from `ROADMAP.md`.

### Files

| File | Change |
|------|--------|
| `src/Console/SyncReporter.php` | Add `hashTree`, `renderContentHint`; extend `planSkillAction` |
| `tests/SyncCommandTest.php` | Add copied-skill drift scenarios (dir pre-created in test setup) |
| `tests/SyncReporterTest.php` | **New (or extend)** — `hashTree` + hint unit tests |
| `ROADMAP.md` | Prune |

---

## Open Questions

1. **Hint file-list cap.** `(content: SKILL.md differs)` is more
   useful than `(content: 1 file differs)` for tiny diffs. For
   large trees it blows up. Cap at 3 filenames before falling back
   to count-only. Decide wording during implementation.

2. **Does the benchmark warrant a CI job?** Probably not — the
   warm-filesystem budget is loose enough that hardware variance
   won't trip it. Keep it as a documented smoke test, not a gate.

---

## Findings

<!-- Notes added during implementation. Do not remove this section. -->
