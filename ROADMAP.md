# Package Boost Roadmap

Forward-looking plan, ordered by release window. Informed by real
feedback from two downstream packages
(`laravel-fluent-validation`, `laravel-js-store`) running
package-boost 0.3.x–0.4.x in anger. Items may shift based on further
feedback or upstream Laravel Boost changes.

Last updated alongside 0.4.1.

## 0.5.0 — foundation polish + shipped skills expansion

Closes most of the remaining pain points surfaced in the April 2026
peer feedback round.

### ~~Runner-agnostic foundation~~ — shipped

Foundation and `package-development` SKILL.md rewritten to
runner-agnostic phrasing, with Boost-specific commands moved to a
dedicated sub-table. Landed under
`specs/0.5.0/runner-agnostic-foundation.md`.

### ~~Ship two more skills~~ — shipped

- ~~`cross-version-laravel-support`~~ — shipped. See
  `specs/0.5.0/cross-version-laravel-support-skill.md`.
- ~~`ci-matrix-troubleshooting`~~ — shipped. See
  `specs/0.5.0/ci-matrix-troubleshooting-skill.md`.

### ~~`.ai/guidelines/` schema docs~~ — shipped

`## Authoring guidelines` section appended to the shipped
`package-development` SKILL. See
`specs/0.5.0/guidelines-schema-docs.md`. 0.5.0 scope complete.

## 0.6.0 — structured output

- **`--format=json`** on `package-boost:sync`. Schema roughly:
  ```json
  {
    "skills":     { "new": [...], "updated": [...], "removed": [...], "unchanged": 24 },
    "guidelines": { ... },
    "mcp":        { "action": "unchanged" },
    "drift": false
  }
  ```
  Lets GitHub Actions / pre-release skills parse drift
  programmatically without regex on glyph lines. Driven by
  fluent-validation peer, deferred from 0.4.0.

- **Composer `post-autoload-dump` auto-sync**. Ship an opt-in
  README snippet + a guarded `package-boost:sync` invocation safe to
  run on `composer install` / `composer update`. Avoids the
  "edited `.ai/*` but forgot to sync" class of PR mistakes at the
  source.

## 0.7.0 — content-drift detection (the B3 rabbit hole)

- **Tree-hash diff for copied skills.** Today, `planSkillAction`
  returns `'unchanged'` for any non-symlink destination regardless
  of content. On filesystems without symlink support (rare but
  real: some Windows + some CI sandboxes), `--check` silently
  passes when skill content has drifted. Fix: recursive hash of
  source dir vs dest dir. Scope creep risk — only tackle when a
  real user reports a miss.

## Ongoing / external

- **Upstream Boost discovery fix.** Boost's
  `Composer::packages()` reads `base_path('composer.json')`, which
  under Testbench resolves to the testbench-core skeleton with no
  `require` entries. Third-party `resources/boost/{skills,guidelines}/`
  content is therefore undiscoverable through Boost itself. Issue
  being filed by the fluent-validation peer on `laravel/boost`.
  Two fix shapes suggested upstream: (a) walk up for a non-skeleton
  `composer.json`, (b) a `Composer::rootComposerJsonPath()` hook
  Testbench can override. Package Boost's shipped-bundling approach
  (0.3.3+) means this doesn't block us, but fixing upstream
  unblocks the wider ecosystem.

- **Deprecation alias for old `boost:update` references.** Old skill
  bundles floating around reference `boost:update`, which doesn't
  exist — the real command is `package-boost:sync`. Either register
  a deprecated alias that prints a migration message, or document
  in a release note. Low urgency.

## Out of scope (for now)

- **Watch mode on `package-boost:sync`.** Fluent-validation peer
  floated it. `--check` + composer auto-sync covers the actual
  CI/contributor ergonomics, and a watch daemon adds noisy
  foreground process management for marginal benefit.

- **Source-attribution in delta output.** Printing
  `(from sandermuller/package-boost 0.3.3)` next to `+` entries.
  Peer confirmed the simpler output already covers the CI use case;
  refactor effort not justified.

- **Distinct exit codes for new vs stale drift.** `any drift = exit 1`
  is the standard CI idiom. No consumer asked for granular codes;
  YAGNI.

- **Raising PHP / Laravel floors.** Stay on the widest constraint
  set downstream packages actually use (`php ^8.2`,
  `illuminate/* ^11.0|^12.0|^13.0`) until Laravel itself drops a
  version the ecosystem has moved off.

## Not planned

These have come up in conversation and been explicitly set aside:

- A web UI / dashboard for sync state.
- Remote skill/guideline sources (fetching from a registry).
- Any runtime dependency on Laravel Boost — package-boost must
  remain usable without Boost installed.
- A plugin architecture for custom sync targets beyond
  `.claude/` / `.github/` / root-level md files.
