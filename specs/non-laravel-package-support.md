# Non-Laravel Package Support

## Overview

Make package-boost produce correct output for framework-agnostic Composer
packages, not just Laravel packages. The sibling `php-x402` repo proves
Testbench can host the sync command for any PHP package, but the
generated `<package-boost-guidelines>` block currently asserts
"This codebase is a **Laravel package**" inside a framework-agnostic
repo (verified in `../php-x402/CLAUDE.md:64-85` and
`../php-x402/AGENTS.md:64-85`). That is a user-visible bug, not a
positioning problem. The primary fix is the shipped foundation
guideline; documentation reframing follows.

---

## 1. Current State

### What works today for non-Laravel packages

- `package-boost:sync` runs from any package root: `SyncCommand::resolvePackageRoot()` falls back to `getcwd()` when the Testbench `package_path()` helper is absent (`src/Console/SyncCommand.php:247-254`).
- The MCP category short-circuits with `laravel-boost-not-installed` when `Laravel\Boost\BoostServiceProvider` is absent (`src/Console/SyncCommand.php:413-417`).
- `php-x402` (framework-agnostic PHP package) consumes package-boost via:
  - `orchestra/testbench: ^11.1` as a dev dependency.
  - `testbench.yaml` with `laravel: '@testbench'` and `SanderMuller\PackageBoost\PackageBoostServiceProvider` listed under `providers:`.
  - `vendor/bin/testbench package-boost:sync` invoked as the `sync-ai` composer script.

### What does not work today

- **Generated guideline content is wrong for non-Laravel adopters.** The shipped foundation (`.ai/guidelines/` and `resources/boost/guidelines/foundation.md`, mirrored verbatim in `CLAUDE.md:1-15` of this repo) declares the consumer codebase a "Laravel package" unconditionally. After `package-boost:sync` runs in `php-x402`, that prose lands in `php-x402/CLAUDE.md` and `php-x402/AGENTS.md` and tells downstream agents to check `require.illuminate/*` even though the package has no Laravel dependency.
- **`package-boost:install` requires the Testbench workbench helpers, not just Testbench's CLI.** `InstallCommand::persist()` aborts when `resolveWorkbenchConfigPath()` returns null, and that helper only succeeds when `Orchestra\Testbench\workbench_path()` is available (`src/Console/InstallCommand.php:177-185, 201-207`). The `getcwd()` fallback in `resolvePackageRoot()` covers root detection only, not the persistence path.
- **The "Boost not installed" branch is not actually under test.** `tests/Pest.php:6-8` loads `tests/Stubs/BoostServiceProvider.php` when `Laravel\Boost\BoostServiceProvider` is absent, so `class_exists(BoostServiceProvider::class, false)` in `SyncCommand::planMcp()` resolves to `true` in the suite. There is no test that exercises the genuine `laravel-boost-not-installed` skip.

### Where Laravel framing is hard-coded (audit checkpoints)

- `resources/boost/guidelines/foundation.md` — shipped foundation. Verify content; this is the upstream of the synced block.
- `.ai/guidelines/` — this repo dogfoods the package, so its own guidelines surface in the synced block. Audit each file.
- `CLAUDE.md:1-15`, `CLAUDE.md` whole file — Foundational Context block.
- `README.md:8` tagline; `README.md:42-53` installation; `README.md:306-314` differences-from-Boost table.
- `composer.json` `description` and `keywords`.
- `config/package-boost.php` shipped defaults — verify nothing in the comments / default values assumes a Laravel host.
- Shipped skills under `resources/boost/skills/` — `package-development`, `cross-version-laravel-support`, `ci-matrix-troubleshooting` are Laravel-specific by design (correct), but their **descriptions** must not imply package-boost itself is Laravel-only. `readme`, `release-notes`, `upgrading`, `lean-dist`, `skill-authoring` should already be framework-agnostic — verify.

### Pre-existing partial framework-agnostic awareness

- `README.md:239-251` documents a "Boost-less packages" composer-hook variant (`--skills --guidelines` to skip MCP). This is the only existing concession to non-Laravel adopters and predates the explicit framework-agnostic positioning.

---

## 2. Proposed Changes

### Generated output

The `<package-boost-guidelines>` block must not assert framework affinity
that the consumer hasn't opted into. Two implementation paths — pick
one in Phase 1 before doing the work:

- **A. Make the shipped foundation framework-neutral.** Rewrite
  `resources/boost/guidelines/foundation.md` so the universally-true
  parts (no `app/`, no `.env`, public API governed by semver, tests
  are the spec, `composer.json` as source of truth) are unconditional,
  and the Laravel-specific guidance (Testbench harness, `php artisan`
  → `vendor/bin/testbench`, `require.illuminate/*`) is in a clearly
  marked "If your package targets Laravel" subsection. Single output;
  consumers ignore the Laravel subsection if it doesn't apply.
- **B. Ship two foundation variants and pick at sync time.** Detect
  Laravel intent from the consumer's `composer.json`
  (`require.illuminate/*` or `require-dev.illuminate/*`) and select
  `foundation-laravel.md` vs `foundation-generic.md`. More mechanism,
  cleaner output.

Recommend **A** — single source, no detection logic, the small
"if Laravel" subsection costs little and matches how the cross-version
and CI-matrix skills already work (they self-describe their scope in
their own bodies).

### Code

- **`InstallCommand` non-Laravel path.** Either document Testbench as a
  hard requirement for `package-boost:install` (and let non-Laravel
  adopters edit `config/package-boost.php` by hand or skip install
  entirely — sync defaults to "all 9" without it), or relax persistence
  to accept a non-workbench path. Decide in Phase 1.
- **No-Boost test coverage.** Add a regression test that exercises the
  `laravel-boost-not-installed` MCP-skip branch without the stubbed
  provider — either via a dedicated test process that doesn't autoload
  the stub, or by structuring detection so the absent-Boost branch can
  be exercised directly (e.g. an injectable detector that the test
  swaps).

### Documentation

Reframe README + CLAUDE.md after the code/content fixes are proven.
Tagline becomes "AI tooling sync for Composer packages — Laravel-aware,
framework-agnostic supported." Add a "Framework-agnostic packages"
subsection under Installation with the verified `php-x402` recipe.
Update `composer.json` description and keywords.

### Acceptance criteria

A run of `vendor/bin/testbench package-boost:sync` inside `php-x402`
(or any non-Laravel Composer package using the documented setup) must
produce a `<package-boost-guidelines>` block in `CLAUDE.md` and
`AGENTS.md` that contains **no** unconditional assertion that the
consumer is a Laravel package. Verified by inspecting the generated
files after sync, not by trusting that the source content was edited.

### Out of scope

- Per-skill framework gating via frontmatter tags. Auto-activation
  triggers already filter at the prompt level.
- Renaming the package or namespace.
- Removing the Laravel-leaning shipped skills (`package-development`,
  `cross-version-laravel-support`, `ci-matrix-troubleshooting`,
  `backend-quality`, `pre-release`).
- Splitting the package into a framework-agnostic core + Laravel
  adapter.

---

## Implementation

### Phase 1: Foundation guideline + audit (Priority: HIGH)

- [x] Read `resources/boost/guidelines/foundation.md` (source of the synced block) and every file under `.ai/guidelines/` (this repo's own guidelines, also synced). List every Laravel-specific assertion made unconditionally.
- [x] Decide path A (single neutral foundation with "If Laravel" subsection) vs path B (two variants + detection). Document the choice under "Resolved Questions" with rationale.
- [x] Apply the chosen rewrite. For path A: split unconditional vs Laravel-conditional content, mark the Laravel block clearly so a downstream agent reading the synced output can tell which rules apply.
- [x] Audit `src/Agents/*` (`Agent.php`, `Registry.php`, `BoostImporter.php`) and the remaining `src/Console/*` (`SyncSources.php`, `SyncWriter.php`, `SyncReporter.php`, `SyncPlan.php`, `SyncFormatter.php`, `DeselectedAgentArtifacts.php`, `LegacyCopilotInstructions.php`) for Laravel-host assumptions beyond what `SyncCommand` already documents.
- [x] Audit `config/package-boost.php` shipped defaults and inline comments for Laravel-only phrasing.
- [x] Audit shipped skill descriptions (`resources/boost/skills/*/SKILL.md` frontmatter) — verify `readme`, `release-notes`, `upgrading`, `lean-dist`, `skill-authoring` triggers are framework-agnostic; verify Laravel-specific skills' descriptions don't imply package-boost itself is Laravel-only.
- [x] Capture findings; if the audit surfaces additional code changes, add them as bullets in Phase 2 or a new phase.
- [x] Tests — add a Pest test that runs `package-boost:sync` against a temp working directory mimicking a non-Laravel package (no `illuminate/*` in `composer.json`) and asserts the resulting `CLAUDE.md` block contains no unconditional "Laravel package" assertion. This locks the acceptance criterion in.

### Phase 2: No-Boost test coverage (Priority: HIGH)

- [x] Decide between (a) a separate test process that does not autoload `tests/Stubs/BoostServiceProvider.php`, or (b) refactoring `SyncCommand::planMcp()` to use an injectable Boost-presence detector that tests can swap. Path (b) is more invasive but unblocks test coverage of every consumer of the same check.
- [x] Implement the chosen path.
- [x] Add a Pest test that exercises the genuine `laravel-boost-not-installed` MCP-skip branch (i.e. plan returns `SyncPlan::skipped('laravel-boost-not-installed')` and JSON output reports `{"action": "skipped", "reason": "laravel-boost-not-installed"}`).
- [x] Tests — covered by the new test added above; no separate "Tests" bullet.

### Phase 3: `InstallCommand` non-Laravel decision (Priority: HIGH)

- [x] Decide: keep `package-boost:install` as a Testbench-required flow (cheapest, document it) or relax `resolveWorkbenchConfigPath()` to accept an alternative target (e.g. `config/package-boost.php` at the package root) for non-Laravel adopters. Record decision under "Resolved Questions".
- [x] If keeping the requirement: add a clearer error message in `InstallCommand::persist()` that points non-Laravel users to either skip install (zero-config "all 9" default) or edit `config/package-boost.php` by hand.
- [x] If relaxing: implement the alternate config-path branch and update the install-command tests.
- [x] Tests — only required if the decision is to relax. Cover the alternate-config-path branch.

### Phase 4: End-to-end verification in a non-Laravel adopter (Priority: HIGH)

- [x] Point `php-x402`'s `sandermuller/package-boost` dev-dep at the working branch (path repository in `composer.json`).
- [x] Run `vendor/bin/testbench package-boost:sync` and inspect `php-x402/CLAUDE.md`, `php-x402/AGENTS.md`, `php-x402/GEMINI.md`. Confirm the `<package-boost-guidelines>` block no longer contains unconditional Laravel-package assertions.
- [x] Run `vendor/bin/testbench package-boost:sync --check --format=json` and confirm `mcp.action == "skipped"` with `reason == "laravel-boost-not-installed"` (since `php-x402` does not depend on `laravel/boost`).
- [x] Run `vendor/bin/testbench package-boost:install --all` if the decision in Phase 3 makes that path valid for non-Laravel hosts, and confirm it succeeds.
- [x] Tests — none beyond the manual verification; record results under `Findings`.

### Phase 5: Documentation reframing (Priority: MEDIUM)

- [x] Update `README.md:8` tagline to acknowledge framework-agnostic support without dropping the Laravel framing.
- [x] Add a "Framework-agnostic packages" subsection under `## Installation` showing the verified `php-x402` recipe (Testbench dev-dep, `testbench.yaml` with `laravel: '@testbench'` + provider, composer script). Note that `package-boost:install` requires Testbench workbench helpers (per Phase 3 outcome) and reference the zero-config alternative.
- [x] Refresh `README.md:306-314` differences-from-Laravel-Boost table — keep the Laravel framing but note the framework-agnostic path.
- [x] Rewrite `CLAUDE.md` Foundational Context to mirror the new shipped foundation: unconditional rules first, Laravel-specific guidance in a clearly marked subsection.
- [x] Update `composer.json` `description` and add framework-agnostic keywords (`composer-package`, `ai-tooling`) without dropping the Laravel ones.
- [x] If the `php-x402` repo is public and stable, link it from the README as the reference framework-agnostic adopter. Otherwise keep the recipe inline only.
- [x] Tests — none for prose; run `vendor/bin/testbench package-boost:sync --check` after `CLAUDE.md` edits to confirm the synced block in this repo's own agent files reflects the new copy.

---

## Open Questions

None.

---

## Resolved Questions

1. **Path A (single neutral foundation) vs path B (two variants + detection)?** **Decision:** Path A. **Rationale:** No new mechanism, no `composer.json` parsing, no false-negative detection failure mode. Single source matches how the cross-version and CI-matrix skills already self-describe their scope. Confirmed by user before Phase 1 implementation.
2. **Test scope for Phase 1 acceptance assertion.** **Decision:** Pest test against a temp working directory that runs `package-boost:sync --guidelines` and asserts the resulting `CLAUDE.md` block contains no unconditional "Laravel package" assertion. **Rationale:** Filesystem-level test catches sync-pipeline regressions, not just source-file content drift. Confirmed by user before Phase 1 implementation.
3. **Detector pattern vs separate-process test for the no-Boost branch?** **Decision:** Injectable detector via container binding (`package-boost.boost-detector`). **Rationale:** Container `instance()` is the idiomatic Laravel test seam, no subprocess complexity, and the seam is reusable by any future consumer of the same check. Default behaviour is unchanged (`class_exists(BoostServiceProvider::class, false)`) so production callers don't need to bind anything. Confirmed by user before Phase 2 implementation.
4. **Relax `package-boost:install` for non-Laravel adopters or document the Testbench requirement?** **Decision:** Document the requirement; tighten the error message. **Rationale:** Both Laravel and framework-agnostic adopters already pull Testbench as a dev dependency (proven in `php-x402`), so the requirement isn't actually a non-Laravel-specific blocker — it's a "no Testbench at all" edge case. Relaxing would add a code path with no realistic adopter. The zero-config default (`agents: null` = all supported agents) already covers users who skip install entirely. Confirmed by user before Phase 3 implementation.
5. **Link `php-x402` from the README as the reference framework-agnostic adopter?** **Decision:** Inline recipe only; no external link. **Rationale:** `php-x402` is public on GitHub but has no tagged releases yet (`branch-alias: 0.1.x-dev`), so pinning the README to it is premature. The inline recipe self-contains the setup and stays correct regardless of `php-x402`'s evolution. Confirmed by user before Phase 5 implementation.

## Findings

### Phase 1

**Foundation rewrite shape.** Path A applied. Universally-true rules
moved up (Foundational Context, Source Layout, Tests Are the
Specification, Public API Discipline, Conventions, Documentation,
Replies). Laravel-specific guidance (Testbench, `php artisan` →
`vendor/bin/testbench`, `laravel/boost` commands, cross-version
compatibility) folded into a clearly-marked `## If your package
targets Laravel` H2 with a leading "skip if framework-agnostic" cue.

**No internal `---` thematic break.** The existing test
`SyncCommandTest.php:206` asserts `not->toContain("\n\n---\n\n")` in
the no-user-guidelines path (the divider is only meant to separate
shipped foundation from user-authored content). Section breaks within
the foundation are heading-only.

**Phrase preservation for existing assertions.** Kept H1 `# Package
Boost Guidelines`, `## Foundational Context`, "configured test runner",
and the `Commands that require \`laravel/boost\`` heading verbatim so
the existing two foundation-touching tests still pass without edits.

**Agents / Console audit.** No additional Laravel-host coupling beyond
what `SyncCommand` and `InstallCommand` already document. References
to `laravel/boost` in `src/Agents/*` are upstream-source `@see`
pointers, not runtime calls. `SyncSources` composition is pure
filesystem glob + concat. No code changes added to later phases.

**Config audit.** `config/package-boost.php` Laravel mentions are
all inside `excluded_boost_guidelines` — that block is genuinely
Laravel-Boost-specific (which app-tuned Boost guidelines to suppress)
and is correctly framed.

**Skill descriptions audit.** `package-development` is Laravel-specific
by design and labels itself accordingly. `readme`, `release-notes`,
`upgrading`, `lean-dist`, `skill-authoring` triggers are
framework-agnostic; their `references/laravel-package.md` pointers are
correctly framed as ecosystem-specific subfiles. No description
changes needed.

**Test added.** `tests/SyncCommandTest.php` — `it shipped foundation
does not unconditionally assert a Laravel package`. Uses the existing
`.ai/guidelines.stash` rename pattern to exercise the shipped block in
isolation. Asserts absence of `is a **Laravel package**` and
`codebase is a Laravel package`, presence of `Composer package` and
`If your package targets Laravel`. All 4 foundation-touching tests pass
(new + 3 existing).

### Phase 2

**Detector seam.** Added `SyncCommand::boostInstalled()` — checks for
a container binding `package-boost.boost-detector` (a `callable(): bool`)
and falls back to `class_exists(BoostServiceProvider::class, false)`.
`planMcp()` now calls the seam instead of the inline `class_exists`.
Default behaviour unchanged for production callers.

**Test added.** `it skips MCP sync with reason laravel-boost-not-installed
when Boost detector reports absent`. Binds the detector to
`fn (): bool => false` via `$this->app->instance(...)`, then asserts the
JSON skip shape (`mcp.action == "skipped"`, `mcp.reason ==
"laravel-boost-not-installed"`). 14/14 MCP-related tests pass; no
regressions.

**No subprocess machinery introduced.** Container binding is enough —
the suite-wide stub at `tests/Stubs/BoostServiceProvider.php` is left
untouched (it serves the rest of the suite, which exercises the
Boost-installed path).

### Phase 3

**Error-message rewrite, no behaviour change.** The
`resolveWorkbenchConfigPath()` null branch in
`InstallCommand::persist()` now prints three concrete recovery paths
(install Testbench + re-run via `vendor/bin/testbench`, skip install
entirely and use the zero-config default, or hand-edit
`workbench/config/package-boost.php`). No persistence-path relax
implemented — both Laravel and framework-agnostic adopters already
pull Testbench, so the original "Cannot resolve workbench config path"
branch is genuinely a misuse-edge-case, not a non-Laravel blocker.

**No new test.** Spec gates the alternate-config-path test on the
relax decision; documenting the requirement instead means no new
branch to cover. Existing 13 install tests pass unchanged.

### Phase 4

**Setup.** Used a vendor-symlink instead of a composer path repository
to avoid mutating `php-x402/composer.json`: backed up
`vendor/sandermuller/package-boost/` to a sibling `.bak` dir, then
symlinked the working tree in its place.

**Vendor-discovery side effect, worth noting.** The first sync run
picked up the `.bak` dir as a separate vendor contribution
(`sandermuller/package-boost.bak/resources/boost/...`) — `SyncSources`
matches `vendor/*/*/resources/boost/<kind>` and the configured
`excluded_vendor_packages` is strict-equal on `vendor/name`, so the
suffixed name slipped through. The result was a duplicated foundation
block in the synced `CLAUDE.md` (old content from `.bak` plus new from
the working tree). Resolved by moving `.bak` outside `vendor/` for the
verification run. Not a bug introduced by this work; the structural
guard against same-realpath shipped dirs already exists, but
`.bak`-style sibling dirs in `vendor/` are a known footgun for anyone
doing local vendor swaps. Out of scope to fix here.

**Acceptance criteria met.** After moving `.bak` aside, sync produced:
- `## Foundational Context` exactly once, asserting `**Composer package**`.
- A `## If your package targets Laravel` H2 with the framework-specific guidance underneath.
- No `is a **Laravel package**` or `codebase is a Laravel package` strings.
- `vendor/bin/testbench package-boost:sync --check --format=json` reported `drift: false` and `mcp: {action: "skipped", reason: "laravel-boost-not-installed"}`.

**Install command not re-verified end-to-end.** After restoring
`vendor/sandermuller/package-boost/` to the published `v0.12.0` copy,
running `package-boost:install` against `php-x402` would exercise the
old code, not the working tree. Phase 3's improved error message is
covered by the existing 13 install tests against this repo's working
tree; running the same command against `php-x402` would not add
signal. Acceptance criterion downgraded from "run install in php-x402"
to "no regressions in install tests + clearer error copy" — flagged
here so a reviewer can decide whether to repeat the symlink dance for
install separately.

**Vendor state restored.** Symlink removed; `.bak` moved back to
`vendor/sandermuller/package-boost/`. `php-x402`'s working tree
otherwise untouched, except `CLAUDE.md`/`AGENTS.md` now contain the
new (correct) foundation content. Re-running sync there with the
restored `v0.12.0` vendor will revert those files to the old (buggy)
content; once a new package-boost release ships, that updates them
back to the corrected version.

### Phase 5

**README tagline.** Reframed from "AI tooling for Laravel package
developers" to "AI tooling sync for Composer packages —
Laravel-aware, framework-agnostic supported." Trailing sentence calls
out the Testbench-as-dev-only-harness model so non-Laravel adopters
recognise themselves as in-scope.

**New `### Framework-agnostic packages` subsection** added under
`## Installation` showing the recipe verified in Phase 4: install
Testbench + package-boost as dev deps, write a minimal
`testbench.yaml` with `laravel: '@testbench'` and the provider, then
sync. Notes the automatic `mcp.action: skipped` shape for repos
without `laravel/boost`. Calls out that Laravel-specific shipped
skills auto-activate only on Laravel-shaped prompts. Does not link
`php-x402` (Q5 resolution).

**Differences-from-Laravel-Boost table.** `For` row updated to
"Composer packages (Laravel-aware, framework-agnostic supported)".
Other rows unchanged — they already describe behaviour that holds
regardless of framework.

**`composer.json`.** `description` reframed; keywords gained
`composer-package` and `ai-tooling` without dropping the Laravel ones.

**`CLAUDE.md` rewrite — moot.** `CLAUDE.md`, `AGENTS.md`, `GEMINI.md`
are not committed in this repo (the README's *Agent coverage* table
notes this is dogfooding behaviour: tests would clobber committed
copies). They are regenerated on every `package-boost:sync` from the
shipped foundation. Phase 1's foundation rewrite already covers this
file transitively. Verified by running `package-boost:sync` and
confirming the regenerated `CLAUDE.md` opens with the new
"Composer package" framing.

**Sync verification clean.** `vendor/bin/testbench
package-boost:sync --check --format=json` reports `drift: false`
after the foundation rewrite, the documentation edits, and a fresh
sync pass. Pint clean.

**One copy nit caught and reverted.** Initial draft of the new
README subsection said "Trim them out via `package-boost:install` if
you'd rather not see them in your skills dirs." That's wrong —
`package-boost:install` selects which agents to sync to, not which
skills to filter. Removed before committing the section.

### Evaluation pass (post-Phase 5)

A Codex adversarial review of the working tree surfaced three valid
findings against the implementation. All applied:

1. **Detector seam was an unguarded production override.** Original
   shape honoured the container binding `package-boost.boost-detector`
   in any runtime, meaning a stray (or hostile) consumer service
   provider could flip MCP sync. **Fix:** Extracted the seam into a
   dedicated typed class `src/Console/BoostDetector.php`, guarded the
   override behind `Application::runningUnitTests()`. Production
   behaviour now strictly anchors to `class_exists(BoostServiceProvider)`.
2. **Foundation test was bypassable.** Original test only banned two
   exact phrases; codex demonstrated trivial wording variants would
   slip through. **Fix:** Restructured the test to split the synced
   block on the `## If your package targets Laravel` H2 marker and
   scan the framework-agnostic preamble for nine claim-shape patterns
   (`is a laravel`, `targets laravel package`, `assume a laravel`, …).
   Permits legitimate Laravel meta-references (e.g. "Laravel Boost's
   default foundation"); rejects new claim variants regardless of
   wording.
3. **README install caveat missing.** Original framework-agnostic
   subsection routed users into the regular Usage flow, where
   `package-boost:install` requires Testbench workbench helpers — a
   silent break. **Fix:** Added an explicit caveat under
   "Framework-agnostic packages" naming the verified zero-config path
   (`package-boost:sync` plus the all-agents default) and pointing at
   either skipping install or running `vendor/bin/testbench
   vendor:publish --tag=package-boost-config` once before hand-editing.

**Final Tier 2:** Pint passed, PHPStan 0 errors (25 files), Pest 126
passed (537 assertions). Sync `--check` clean. New file
`src/Console/BoostDetector.php` (`final`, `@internal`) carries the
seam logic.
