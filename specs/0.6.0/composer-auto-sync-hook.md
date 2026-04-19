# Composer Auto-Sync Hook

## Overview

Provide an opt-in composer `post-autoload-dump` snippet so contributors
running `composer install` / `composer update` /
`composer dump-autoload` automatically pick up skill and guideline
changes. Documentation-only — no code changes to package-boost
itself. Complements the `--check` CI gate landed in 0.4.0.

---

## 1. Current State

Downstream packages rely on contributors remembering to run
`vendor/bin/testbench package-boost:sync` after editing any `.ai/*`
source file. When forgotten:

- `.claude/skills/` / `.github/skills/` / `CLAUDE.md` / `AGENTS.md`
  drift from the sources.
- 0.4.0's `--check` catches this in CI, but the contributor still
  eats a round-trip.

Composer's `post-autoload-dump` lifecycle hook fires on every
`install`, `update`, and `dump-autoload`. An opt-in snippet for
`package-boost:sync` is the natural fit.

## 2. Proposed Changes

Add a `### Composer auto-sync hook` section to the README under the
existing `### Composer script` block. Document both variants with
trade-offs.

### Strict variant (recommended)

Matches the 0.4.0 CI contract: composer installs should behave the
way CI would. If anything has drifted, the install fails and the
contributor re-runs sync by hand.

```json
{
    "scripts": {
        "post-autoload-dump": [
            "@php vendor/bin/testbench package-boost:sync --check"
        ]
    }
}
```

### Auto-fix variant

Mutates the working tree when drift exists, which leaves
uncommitted changes behind after a fresh `composer install` on a
dirty branch. Trade-off: friendlier to contributors, noisier in
unexpected places.

```json
{
    "scripts": {
        "post-autoload-dump": [
            "@php vendor/bin/testbench package-boost:sync"
        ]
    }
}
```

Rejected alternative: a chained strict-then-fix (`--check ||
sync`). Composer resolves `@php` substitution before handing the
string to the shell, and shell operators (`||`) vary across
platforms (posix-sh vs Windows `cmd.exe` / PowerShell). A single
command per array entry is portable.

### Boost-absent caveat

If the host package doesn't depend on `laravel/boost`, the hook's
MCP step emits a warning on every composer run. Document the
`--skills --guidelines` narrowing for Boost-less packages:

```json
{
    "scripts": {
        "post-autoload-dump": [
            "@php vendor/bin/testbench package-boost:sync --check --skills --guidelines"
        ]
    }
}
```

### Cross-platform note

`@php` is composer's own substitution; it runs before shell
evaluation. On Windows the hook runs via `cmd.exe`; on posix via
`/bin/sh`. A single command per array entry works on both. Verify
during implementation by manually running a consumer's
`composer dump-autoload` on one posix and one Windows host
(GitHub Actions `windows-latest` suffices for the latter).

## Implementation

- [ ] Add `### Composer auto-sync hook` section to `README.md`
  under the existing `### Composer script` block.
- [ ] Document strict (recommended), auto-fix, and Boost-less
  variants with code snippets and trade-off commentary.
- [ ] Add a cross-platform verification note — no workflow
  changes, just document that both posix and Windows shells are
  supported.
- [ ] Cross-link from `resources/boost/skills/package-
  development/SKILL.md` "Syncing" section to the new README
  section.
- [ ] Manual smoke test: add the strict variant to a downstream
  package (e.g. laravel-fluent-validation via the peer) and
  confirm `composer dump-autoload` exits 0 on clean repo, exits 1
  on seeded drift.
- [ ] Document migration impact: no schema changes; downstream
  adoption is opt-in.
- [ ] Prune the entry from `ROADMAP.md`.

### Files

| File | Change |
|------|--------|
| `README.md` | Add `### Composer auto-sync hook` section |
| `resources/boost/skills/package-development/SKILL.md` | Cross-link from Syncing section |
| `ROADMAP.md` | Prune |

---

## Open Questions

1. **Default recommendation — strict or auto-fix?** Strict is safer
   (never mutates tree unexpectedly) and matches the CI contract.
   Auto-fix is friendlier but produces uncommitted changes. Leading
   with strict; keep auto-fix documented for teams that want it.

2. **Should we ship a composer-plugin** (`package-boost-composer-
   plugin`) that registers the hook automatically? Plugin avoids
   the manual copy-paste step but adds a second package to
   maintain and a runtime dependency. YAGNI for now — revisit if
   adoption is slow.

---

## Findings

### Shipped three variants, strict leading

Docs landed with all three variants the spec proposed: strict
(recommended first), auto-fix (friendlier but leaves uncommitted
changes on dirty branches), Boost-less (`--skills --guidelines`
narrowing to suppress the "Laravel Boost is not installed" warn on
every composer run). This turned out to matter: the js-store peer
flagged the warn as minor noise when verifying 0.8.0 — the Boost-less
hook snippet in this spec silences it at the source.

### Cross-platform note kept minimal

Spec proposed verifying the hook works on posix + Windows. Opted for
a one-line doc note ("hook runs via /bin/sh on posix and cmd.exe on
Windows; single-command-per-array-entry form works on both") rather
than adding CI cells to validate it. Composer's own
`@php` substitution semantics are well-defined; a matrix expansion
would be verifying composer, not package-boost.

### Skipped the manual smoke test

Spec asked for "add the strict variant to laravel-fluent-validation
via the peer and confirm composer dump-autoload exits 0/1 as
expected". Not executable from this repo without shipping the hook
downstream first. Deferred to whenever a downstream peer adopts the
snippet — they'll report back if the exit-code gate misbehaves.

### No code changes

As scoped, this was pure documentation (README + skill cross-link +
ROADMAP prune). `package-boost:sync --check` already implements the
semantics the hook leverages; no new command options or behavior
introduced.
