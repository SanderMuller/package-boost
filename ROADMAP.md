# Package Boost Roadmap

Forward-looking plan. Items may shift based on peer feedback
(`laravel-fluent-validation`, `laravel-js-store`, `php-x402`) or
upstream Laravel Boost changes. For shipped work, see `CHANGELOG.md`.

## Open

- **`package-boost:doctor --fix` autoremediation.** Today
  `doctor` fans out the checks scattered across `sync --check`,
  `install`, and `lean` and exits non-zero on any failure — but
  the operator still has to run the remediation command for
  every category by hand. A `--fix` flag would resolve the
  mechanically-safe drift in one pass: re-run sync to clear
  stale generated content, prune deselected-agent orphans,
  rewrite the managed `.gitattributes` block, and delete the
  legacy Copilot file. Vendor skill collisions, MCP detection
  state, and SKILL.md frontmatter issues stay report-only —
  those need a human decision. Scope: a single new flag on the
  existing command, no new top-level command, exit code stays
  `0` when every fix-eligible category resolves cleanly.

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

- **Per-agent skill opt-in/opt-out.** Today the agent registry is
  the granularity knob — pick which agents receive the full
  shipped bundle. Per-skill subsetting per agent multiplies
  config surface and drift-detection complexity for a use case
  no consumer has raised.

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
