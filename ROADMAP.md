# Package Boost Roadmap

Forward-looking plan. Items may shift based on peer feedback
(`laravel-fluent-validation`, `laravel-js-store`) or upstream Laravel
Boost changes. For shipped work, see `CHANGELOG.md`.

## Open

- **`lean-dist` skill** — shipped skill that on-ramps consumers
  to `stolt/lean-package-validator` (lpv) for `.gitattributes`
  hygiene, plus an AI-era `.lpv` glob seed (`.ai/`, `.claude/`,
  `AGENTS.md`, `CLAUDE.md`, `.cursor/`, etc.) that lpv's defaults
  don't cover. lpv already ships three package-boost-format skills
  (`creating-` / `updating-` / `validating-gitattributes-file`)
  picked up by vendor-discovery once installed; this skill
  handles the install + AI-era patterns + edge-case guard so the
  three lpv skills can take over the per-command work.

## Ongoing / external

- **Upstream Boost discovery fix.** Boost's
  `Composer::packages()` reads `base_path('composer.json')`, which
  under Testbench resolves to the testbench-core skeleton with no
  `require` entries. Third-party `resources/boost/{skills,guidelines}/`
  content is therefore undiscoverable through Boost itself. Issue
  to be filed by the fluent-validation peer on `laravel/boost`.
  Two fix shapes suggested upstream: (a) walk up for a non-skeleton
  `composer.json`, (b) a `Composer::rootComposerJsonPath()` hook
  Testbench can override. Package Boost's shipped-bundling approach
  (0.3.3+) sidesteps the bug, and vendor-package discovery
  provides a native path that doesn't rely on Boost's Composer
  resolver — but fixing upstream unblocks the wider ecosystem for
  Boost-only consumers.

## Sunset

- **`boost:update` alias removal** — introduced as a deprecated
  alias in 0.8.0, removal target 0.11.0 (three minor releases).
  Nothing to do until we cut 0.11.0.

## Out of scope (for now)

Deliberate non-choices worth documenting so they don't resurface
as "why don't we…".

- **Watch mode on `package-boost:sync`.** `--check` + the
  composer auto-sync hook cover the CI/contributor ergonomics; a
  watch daemon adds noisy foreground process management for
  marginal benefit.

- **Source-attribution in delta output.** Printing
  `(from sandermuller/package-boost 0.3.3)` next to `+` entries.
  Peer confirmed the simpler output covers the CI use case;
  refactor cost not justified.

- **Distinct exit codes for new vs stale drift.** `any drift = exit 1`
  matches the standard CI idiom. No consumer asked for granular
  codes.

- **Raising PHP / Laravel floors.** Stay on the widest constraint
  set downstream packages actually use (`php ^8.2`,
  `illuminate/* ^11.0|^12.0|^13.0`) until Laravel itself drops a
  version the ecosystem has moved off.

## Not planned

- A web UI / dashboard for sync state.
- Remote skill/guideline sources (fetching from a registry).
- Any runtime dependency on Laravel Boost — package-boost must
  remain usable without Boost installed.
- A plugin architecture for custom sync targets beyond
  `.claude/` / `.github/` / root-level md files.
