# Changelog

All notable changes to `package-boost` will be documented in this file.

## 0.10.0 - 2026-05-01

### What's new

#### Multi-agent sync

Three guideline files (`CLAUDE.md`, `AGENTS.md`, `GEMINI.md`) and
six unique skill dirs:

| Agent          | Guidelines  | Skills dir       |
|----------------|-------------|------------------|
| Claude Code    | `CLAUDE.md` | `.claude/skills` |
| Cursor         | `AGENTS.md` | `.cursor/skills` |
| GitHub Copilot | `AGENTS.md` | `.github/skills` |
| Codex CLI      | `AGENTS.md` | `.agents/skills` |
| Gemini CLI     | `GEMINI.md` | `.agents/skills` |
| Junie          | `AGENTS.md` | `.junie/skills`  |
| Kiro           | `AGENTS.md` | `.kiro/skills`   |
| OpenCode       | `AGENTS.md` | `.agents/skills` |
| Amp            | `AGENTS.md` | `.agents/skills` |

`.agents/skills` is shared across Codex, Gemini, OpenCode, and Amp —
sync writes there once and dedupes.

#### `package-boost:install` command

Interactive picker for the agent set:

```bash
vendor/bin/testbench package-boost:install

```
Defaults pre-fill in this order: existing `package-boost.agents`
config → import from `laravel/boost`'s `boost.json` if Boost is
installed → detection-marker scan against the project (`.cursor/`,
`.kiro/`, `CLAUDE.md`, etc.) → all 9. The selection persists to
`workbench/config/package-boost.php` via a single-line regex-replace
of the `'agents' =>` key, preserving comments and unrelated keys.
Non-interactive flags: `--all`, `--agents=claude_code,cursor`,
`--no-import`. Adversarial config shapes (multi-line array, missing
key, hand-customised formatting) refuse with a clear diagnostic.

#### `agents` config key

```php
// config/package-boost.php
'agents' => null,  // null = all 9; or e.g. ['claude_code', 'cursor']

```
Unknown agent names trigger a non-fatal warning naming each typo and
listing the supported set, so a misconfigured selection is visible
without breaking sync. When `claude_code` is filtered out, MCP sync
is skipped (`--check` reports `claude-not-selected`) — per-agent MCP
serializers are out of scope for this release.

#### Migration: legacy `.github/copilot-instructions.md`

Upstream Boost migrated Copilot guidelines from
`.github/copilot-instructions.md` into `AGENTS.md`. Package-boost
matches that — the legacy file is no longer written. Sync detects
the leftover with our `<package-boost-guidelines>` tag block and
warns. To remove it automatically:

```bash
vendor/bin/testbench package-boost:sync --prune

```
`--prune` refuses if the file has user content outside the block,
**or** if the block has been hand-edited / is stale relative to
current `.ai/` sources. Run a regular `package-boost:sync` first to
refresh the block, then `--prune` to clean up.

#### Deselected-agent artifact warnings

When `package-boost.agents` is narrowed after a previous broader
sync (for example, dropping from "all" to `['claude_code']`), the
generated files for the now-deselected agents stay on disk —
`.cursor/skills/`, `GEMINI.md`, `.mcp.json`, etc. Sync now scans for
these and warns:

```
WARN  Generated artifacts exist for agents NOT in `package-boost.agents`:
  - .cursor/skills/ (14 entries)
  - GEMINI.md (contains <package-boost-guidelines> block)
  - .mcp.json (laravel-boost mcpServers entry present)
  These were synced under a previous selection. Re-include the agent
  or delete the paths manually.

```
Auto-removal is intentionally not done — guideline files may carry
user content outside the package-boost-guidelines block. Surface and
let the user decide.

#### JSON output: new `claude-not-selected` skip reason

`--format=json` adds a stable shape for the gated MCP case:

```json
{ "mcp": { "action": "skipped", "reason": "claude-not-selected" } }

```
Joins the existing `laravel-boost-not-installed` reason. The
schema's `skipped` semantics are unchanged; only the reason
identifier is new.

### Breaking change

`.github/copilot-instructions.md` is no longer written. Existing
consumers will see a warning on the next sync; clean up via
`package-boost:sync --prune` or by deleting the file manually. No
config or API change is required — Copilot reads `AGENTS.md` now,
which package-boost has been writing alongside `CLAUDE.md` since
0.7.0.

### Internals

- `Agents\Registry` is the single source of truth for agent paths,
  detection markers, and selection filtering. The 9 entries are
  frozen against `laravel/boost@8ed9f84` (verified by direct source
  read of `src/Install/Agents/*` and `src/BoostManager.php:22-32`).
- `Agents\BoostImporter` reads `boost.json` at the project root,
  filters against the registry, returns `null` for missing /
  malformed / unknown-only inputs.
- `Console\DeselectedAgentArtifacts` enumerates orphans across
  skills, guidelines, and MCP for the warning above.
- `Console\LegacyCopilotInstructions` owns the legacy-file detect /
  prune contract, with the prune-safety check that compares the
  file's tag block to the freshly composed expected block.
- `SyncCommand`'s class cognitive complexity stays under PHPStan's
  budget by extracting the post-categories rendering and final-exit
  decisions into named helpers.

**Full Changelog**: https://github.com/SanderMuller/package-boost/compare/0.9.0...0.10.0

## 0.9.0 - 2026-04-19

### Package Boost v0.9.0

Discovers skills and guidelines shipped by sibling packages.
`package-boost:sync` now walks `vendor/*/*/resources/boost/` and
merges contributions between the shipped defaults and the host's
`.ai/`. First native path that doesn't rely on Laravel Boost's
Composer resolver, sidestepping the testbench-skeleton discovery
bug that blocks Boost's own scan under package development.

#### What's new

##### Vendor-contributed skills and guidelines

Any installed Composer package that ships
`resources/boost/skills/<name>/SKILL.md` or
`resources/boost/guidelines/*.md` is now picked up by
`package-boost:sync` automatically. Load order:

1. Package-boost's shipped defaults
2. Vendor packages (alphabetical by `vendor/name`)
3. Host `.ai/`

For **skills**, later entries override earlier ones on name
collisions — `host/.ai/skills/<name>` always wins over a vendor
skill of the same name. For **guidelines**, each source
contributes its own block and they concatenate in load order,
separated by `---`.

This means a package author can ship, for example, a
`resources/boost/skills/fluent-validation-testing/SKILL.md`
bundled with their library, and downstream consumers running
`package-boost:sync` will pick it up in their `.claude/skills/`
and `.github/skills/` directories alongside their own content —
no extra wiring, no manual copy.

##### Configuration

Two new keys in `config/package-boost.php`, both shipped with
sensible defaults so existing consumers see the behavior
activate on `composer update` with no publish step required:

```php
'discover_vendor_packages' => true,

'excluded_vendor_packages' => [
    'sandermuller/package-boost',
],


```
- `discover_vendor_packages` — toggle off to restore 0.8.x
  behavior (shipped + host `.ai/` only). No consumer has asked
  for this yet; the flag exists as an escape hatch.
- `excluded_vendor_packages` — skip specific packages by
  `vendor/name`. Default excludes package-boost itself so a
  transitively-installed copy can't double-ingest shipped
  content through its own `resources/boost/`.

##### Self-ingestion guard

Beyond the configurable exclude list, `SyncSources::vendorDirs()`
realpath-compares each vendor match against the shipped
`resources/boost/<kind>` directory and drops exact matches. This
is structural, not user-tunable — so even a consumer who
deliberately clears `excluded_vendor_packages` can't double-
ingest shipped skills through a symlinked dev checkout or a
transitive dep surfacing package-boost under its own `vendor/`
tree.

##### Why now

Laravel Boost's upstream package discovery reads
`base_path('composer.json')`, which under Testbench resolves to
the testbench-core skeleton with no `require` entries. That
means `resources/boost/{skills,guidelines}/` content shipped by
third-party packages is invisible to Boost itself when run via
`vendor/bin/testbench boost:install` inside a package repo.

Package Boost's shipped-bundling approach (0.3.3+) carried
package-boost's own skills through this gap. Vendor discovery
(0.9.0) extends the same idea to arbitrary packages — any
dependency that ships `resources/boost/` gets surfaced, whether
Boost is installed or not.

#### Upgrading

```bash
composer update sandermuller/package-boost
vendor/bin/testbench package-boost:sync


```
The first sync after upgrading may add skills or guidelines
contributed by your existing dependencies. Review the generated
`.claude/skills/` / `.github/skills/` / guideline blocks; if a
vendor contribution isn't wanted, add its `vendor/name` to
`excluded_vendor_packages` in a published config file.

If you previously relied on your `.ai/skills/<name>` containing
the only copy of a given skill name, behavior is unchanged —
host `.ai/` still wins on collisions.

#### Compatibility

No breaking changes. No schema changes to `--format=json`
output — vendor contributions flow through the existing
`skills` / `guidelines` arrays indistinguishably from shipped
and host entries, so CI gates on the JSON drift report continue
to work.

Config file additions are forward-compatible: consumers on a
published 0.8.x `package-boost.php` keep working because
`applyUserConfigOverrides` uses `array_replace` (missing keys
retain shipped defaults).

#### Internals

- `SyncSources::vendorDirs()` globs
  `vendor/*/*/resources/boost/<kind>` with `GLOB_ONLYDIR`, sorts
  by `vendor/name`, and filters via exclude list + realpath
  guard.
- Existing skill planning / writing / drift-reporting code
  paths are untouched — vendor dirs simply appear in the
  `SyncSources::dirs()` sequence between shipped and host
  `.ai/`, so all of `planSkills`, `planGuidelines`, and
  `--check` drift detection work without modification.
- Five new tests in `tests/SyncCommandTest.php`:
  discovery, host-wins-on-collision, guideline merge,
  `--check` drift reporting, `discover_vendor_packages=false`,
  `excluded_vendor_packages` respect, and the self-mirror
  realpath guard. Total suite: 53 passing, 184 assertions.

## 0.8.1 - 2026-04-19

### Package Boost v0.8.1

Hygiene release. No code changes, no schema changes. Ships the repo
housekeeping that accumulated since 0.8.0 — nothing here requires
downstream action.

#### What's new

##### Composer auto-sync hook documented

New *Composer auto-sync hook* section in the README under *Composer
script*, documenting three variants that pair well with the 0.4.0
`--check` gate:

- **Strict (recommended)** — `@php vendor/bin/testbench package-boost:sync --check` as a `post-autoload-dump` entry. Fails
  the install if anything drifted.
- **Auto-fix** — runs the sync without `--check`; friendlier but
  leaves uncommitted changes on a dirty branch.
- **Boost-less** — narrows the hook to `--check --skills --guidelines` so Boost-less packages don't see the "Laravel Boost
  is not installed" warn on every composer run (direct response to
  the js-store peer's 0.8.0 verification feedback).

Cross-platform note: the hook form works on both posix (`/bin/sh`)
and Windows (`cmd.exe`). Chained shell operators (`&&`, `||`) are
not portable across composer's shell layers — use separate array
entries if you need multiple steps.

Cross-linked from the shipped `package-development` skill's
*Syncing* section so discoverability works both from the README and
from the skill bundle downstream users see.

##### `CHANGELOG.md`

`CHANGELOG.md` now lives at the repo root, auto-maintained by a new
`.github/workflows/update-changelog.yml` workflow. On each published
GitHub release the workflow prepends the release body to
`CHANGELOG.md` and commits back to `main` via
`stefanzweifel/changelog-updater-action`. Seeded with entries from
`v0.4.0` through `v0.8.0`.

README now links to `CHANGELOG.md` between *How It Differs* and
*License*.

##### shields.io status badges

README header carries four badges matching the sibling package's
style: Packagist version, test workflow status, code-style workflow
status, Laravel compatibility. Existing content-only badge (Laravel
Compatibility) updated to `?style=flat` for visual consistency.

##### Package-boost dogfoods itself

`.ai/guidelines/` and `.ai/skills/` are now committed:

- **Guidelines:** `release-automation` (explains the
  `CHANGELOG.md` automation), `verification-before-completion`
  (evidence-before-claims rule, mirrored from laravel-fluent-
  validation with a preamble note pointing at the canonical copy).
- **Skills:** `ai-guidelines`, `backend-quality`, `bug-fixing`,
  `code-review`, `codex-review`, `evaluate`, `implement-spec`,
  `pr-review-feedback`, `pre-release`, `write-spec` — 10 workflow
  skills carried over from the sibling, adapted where they
  referenced fluent-validation internals (backend-quality's
  benchmark group → rector; bug-fixing's FluentRule example →
  SyncCommand fixture; pr-review-feedback's GraphQL repo name;
  pre-release rewritten for package-boost's matrix). The
  `autoresearch` skill was deliberately skipped — its premise is
  autonomous perf-optimization with a benchmark harness, which
  package-boost doesn't have.

`testbench.yaml` is now committed (previously gitignored) so
contributors can run `vendor/bin/testbench package-boost:sync`
without manual setup.

Generated outputs (`CLAUDE.md`, `AGENTS.md`, `.github/copilot- instructions.md`, `.claude/skills/`, `.github/skills/`) remain
gitignored — the sync-command tests exercise those exact filesystem
paths and would clobber any committed copies every `pest` run. Run
sync locally after `composer install`.

`tests/SyncCommandTest.php::wipeArtifacts()` now targets only
test-created fixtures (`test-skill`, `keep-me`, `stale-skill`,
`test.md`) so committed `.ai/` content survives the suite. One test
(`it ships foundation guideline even without a user .ai/guidelines directory`) renames-and-restores the dogfood guidelines dir to
exercise the no-user-guidelines path that downstream consumers hit
before authoring their own content.

#### Upgrading

```bash
composer update sandermuller/package-boost



```
Nothing changes at runtime. Consumers see the README additions and
the new `CHANGELOG.md` link on the next visit to the repo.

If you want the auto-sync hook in your own package, add the
relevant `post-autoload-dump` variant from the README to your
package's `composer.json` `scripts` block.

#### Compatibility

No breaking changes. No schema changes. No config schema changes.
Text and JSON sync output byte-compatible with 0.8.0.

## 0.8.0 - 2026-04-19

Hygiene release. Ships a deprecation alias for the command name that
keeps showing up in stale skill bundles.

## What's new

### `boost:update` → deprecated alias for `package-boost:sync`

```bash
vendor/bin/testbench boost:update
# WARN  boost:update is deprecated and will be removed in a future release. Use package-boost:sync instead.
# Skills:
#   + .claude/skills/package-development
#   ...



```
Several floating skill bundles reference a `boost:update` command
that never existed — the real command has always been
`package-boost:sync`. Before this release, typing `boost:update`
produced Laravel's generic `Command "boost:update" is not defined`
error with no hint at the actual name.

Now it runs the same thing `package-boost:sync` would, with a one-
line migration warning printed first. Hidden from `artisan list` so
users who already migrated don't see it. All options (`--check`,
`--skills`, `--guidelines`, `--mcp`, `--show-unchanged`,
`--format=text|json`) pass through unchanged.

### Sunset schedule

Tracked in `ROADMAP.md` under a new **Sunset** section. Target
removal in **0.11.0** — three minor releases' worth of notice, since
dev-tooling adoption is slow. If you're still typing `boost:update`
by then, the warning will have been in your face for every run in
between.

## Upgrading

```bash
composer update sandermuller/package-boost



```
No action required. If you have scripts or CI invoking
`boost:update`, update them to `package-boost:sync` at your leisure
— both work until 0.11.0.

## Compatibility

No breaking changes. No config schema changes. No changes to
`package-boost:sync` itself.

## Internals

`UpdateCommand` extends `SyncCommand` and overrides only the
registered name / description / hidden flag in its constructor. No
signature duplication — new options added to `SyncCommand` propagate
automatically.

## 0.7.0 - 2026-04-19

Closes a silent-drift hole in `--check` that affected any consumer
on a filesystem without symlink support (Windows-without-dev-mode,
some sandboxed CI runners). Before 0.7.0, `SyncCommand::linkOrCopy`
fell back to a recursive file copy when `@symlink()` failed — and
every subsequent `--check` reported those copied skill directories
as `unchanged` regardless of whether the shipped skill's content
had drifted. CI passed, local content was stale, nobody knew.

## What's new

### Content-drift detection for copied skill destinations

`SyncReporter::planSkillAction` now has a third branch. When a
skill destination exists as a plain directory (not a symlink),
both the source tree and the destination tree are hashed
(`xxh128` per file; dotfiles and dotted subdirectories skipped)
and compared. Drift is reported as an `updated` action with a
`content:` hint.

**Small diffs name the affected files (cap 3):**

```
~ .claude/skills/package-development (content: SKILL.md differs)
~ .claude/skills/package-development (content: SKILL.md differs, rules/a.md added, rules/b.md removed)



```
**Large diffs collapse to counts:**

```
~ .claude/skills/package-development (content: 4 differ, 1 added, 1 removed)



```
Hint is prefixed with `content:` to disambiguate from the existing
`(symlink → ...)` hint shape used for symlink retargets. JSON
output carries the same string in the `hint` field:

```json
{ "target": ".claude/skills/package-development", "hint": "content: SKILL.md differs" }



```
### `tests/tmp/` gitignored

Test fixtures under `tests/tmp/` are now ignored so an aborted
test run (Ctrl-C, fatal) can't leak artefacts into a subsequent
`git add -A` on a release workflow.

## Upgrading

```bash
composer update sandermuller/package-boost



```
Nothing changes for consumers on filesystems with working
symlinks — the new code path is only reached when
`SyncCommand::linkOrCopy`'s symlink attempt fails.

## Migration impact

Consumers running `--check` on a copy-fallback filesystem will see
drift the first time they upgrade, surfacing any content that had
silently diverged from its shipped source. Expected; run
`package-boost:sync` once without `--check` to reconcile.

No change for JSON consumers beyond the new `content:`-prefixed
hint values appearing in `skills.updated[].hint` when content
drift is detected.

## Internals

- `SyncReporter::hashTree(string $dir): array<string, string>` —
  recursive `{ relativePath => xxh128 }` map via Symfony Finder's
  `->sortByName()->ignoreDotFiles(true)`. Skips dotfiles and
  dotted subdirectories; returns `[]` for missing directories.
  `xxh128` rather than SHA/MD5 because this is change-detection,
  not signing — xxh128 is ~20 GB/s vs SHA-256's ~500 MB/s and
  doesn't trip `phpstan-disallowed-calls`.
- `SyncReporter::renderContentHint(array $source, array $dest): string` — bucket classification of added / differ / removed
  files; named-list when total ≤ 3, count summary above.
- Eight new tests covering the hashTree / hint paths, dotted
  subdirectory skip, and a `SyncCommandTest` integration that
  pre-seeds a directory destination with drifted content and
  asserts `--check` exits 1 with the `content:` hint.

## Deferred

- **Plain-file-at-skill-dest detection** — noted in review; if a
  user has a plain file where a skill directory is expected,
  `planSkillAction` still reports `unchanged`. Fixing it would
  require `linkOrCopy` to force-delete the file, which risks
  destroying user data when intent is unclear. Silent `unchanged`
  remains the conservative default.
- **`hashTree(source)` cache across `SKILL_TARGETS`** — the
  source tree is walked 2× per skill (once per target directory)
  on copy-fallback filesystems. Measured at sub-millisecond per
  walk; caching costs complexity for no real user benefit.

## Compatibility

No breaking changes. Text output carries the new `(content: …)`
hint format on previously-silent drift. JSON `hint` string is
additive — consumers already ignoring `hint` see no change; those
displaying it get the new prefix automatically.

## 0.6.1 - 2026-04-19

Documentation-only release. Pins the `--format=json` schema-v1
contract ahead of the first downstream CI integration (landing in
`laravel-fluent-validation` v1.13) — locks the shape so future
consumers don't have to guess what fields mean or hit the same
`mcp`-as-array foot-gun as the pre-integration peer review caught.

## Fixes

### Schema docstring pinned

Three fields that were underspecified in 0.6.0's README now have
explicit semantics:

- **`hint`** is advisory prose, not a command-to-run. For skills
  on `updated` actions: `"symlink → <relative target>"`. For
  guidelines on `updated` / `new`: `"+N lines"` / `"-N lines"` /
  `"content updated"`. No hint on `removed` or `unchanged`. The
  fix for any drift is always `package-boost:sync` without
  `--check` — don't templatize `hint` as auto-fix output.
- **`line_delta`** (guidelines only) is scoped to the
  `<package-boost-guidelines>` block. Since sync only rewrites that
  block, it's effectively the synced-region delta; everything else
  in the target file is never touched. Don't read it as a full-file
  delta.
- **`mcp`** is a single `{ action, target }` object, never an
  array. The previous jq example in the README only drained
  collection-shaped categories; it would have silently missed MCP
  drift.

### Expanded CI example

Replaced the two-branch jq snippet with a complete one that drains
all six collection arrays (`skills` × `{ new, updated, removed }`,
`guidelines` × same) plus the MCP object branch via direct
equality on `action`:

```bash
jq -r '
    (.skills.new, .skills.updated, .skills.removed)[]?.target,
    (.guidelines.new, .guidelines.updated, .guidelines.removed)[]?.target,
    if .mcp.action == "new" or .mcp.action == "updated" then .mcp.target else empty end
' | sort -u | sed "s|^|  - |"



```
## Upgrading

No code changes. Documentation only. Text and JSON output are
byte-compatible with 0.6.0.

## Compatibility

No breaking changes. Schema v1 contract unchanged — this release
just documents what 0.6.0 already returned.

## 0.6.0 - 2026-04-19

Structured output for `package-boost:sync`. CI scripts and release
automation can now parse drift detection results instead of regex-
matching glyph lines. Directly requested by the
`laravel-fluent-validation` peer after 0.4.0's `--check` landed;
deferred two releases while the trigger-word skills (0.5.0) shipped
first.

## What's new

### `--format=json`

```bash
vendor/bin/testbench package-boost:sync --check --format=json



```
Emits a single JSON document on stdout; exit code still signals
drift (0 clean, 1 on any `new`/`updated`/`removed` or MCP action
other than `unchanged`). Warnings (Laravel Boost missing, no
sources found) are reflected in the JSON as `skipped` fields
rather than stderr text.

**Schema v1:**

```json
{
    "schema": 1,
    "check": true,
    "drift": false,
    "skills": {
        "new": [],
        "updated": [],
        "removed": [],
        "unchanged": 24
    },
    "guidelines": {
        "new": [],
        "updated": [],
        "removed": [],
        "unchanged": 3
    },
    "mcp": {
        "action": "unchanged",
        "target": ".mcp.json"
    }
}



```
Field rules:

- `schema` — currently `1`. Bumped on any breaking change to this
  document.
- `check` — echoes the input flag so consumers can distinguish a
  drift report from a post-write report.
- `drift` — logical-OR across categories. `true` if any
  `new`/`updated`/`removed` array is non-empty, or `mcp.action`
  is anything other than `unchanged`.
- Skills & guidelines entries carry `target`, optional `hint`
  (e.g. `"symlink → ../../vendor/..."`, `"+12 lines"`), and
  optional `line_delta` (raw int for guidelines).
- MCP shape is `{ action, target }` — always a single target
  since `.mcp.json` is the only MCP output.
- Arrays are stable-sorted by `target` for deterministic diffs.

### `--show-unchanged` in JSON mode

By default `unchanged` is an `int` count. Passing `--show-unchanged`
toggles it to an array of `{ target }` entries, matching the
per-line text output.

### Skipped categories

When a category has no sources or a precondition fails, it reports
structurally instead of via warn text:

```json
"skills": { "skipped": "no-sources" }
"mcp":    { "action": "skipped", "reason": "laravel-boost-not-installed" }



```
`drift` treats skipped categories as non-drift.

### Invalid format rejection

```bash
vendor/bin/testbench package-boost:sync --format=yaml
# ERROR  Invalid --format value 'yaml'; expected 'text' or 'json'.



```
Exits non-zero with guidance.

## Upgrading

```bash
composer update sandermuller/package-boost



```
Text output is unchanged — existing callers of
`package-boost:sync` see the same glyph + summary format. JSON is
additive.

### CI example

```yaml
- name: Check package-boost sync
  run: |
      report=$(vendor/bin/testbench package-boost:sync --check --format=json || true)
      drift=$(echo "$report" | jq -r '.drift')
      if [ "$drift" = "true" ]; then
          echo "::error::package-boost sync drift detected"
          echo "$report" | jq -r '.guidelines.updated[].target,.skills.new[].target'
          exit 1
      fi



```
## Internals

Substantial refactor inside `src/Console/` — zero user-visible
impact for text consumers:

- **`SyncAction` / `SyncPlan`** — readonly value objects carrying
  per-target action entries and per-category aggregates.
- **`SyncFormatter`** — owns both `renderText` (glyph + summary,
  matching pre-0.6.0 byte-for-byte) and `renderJson` (the new
  structured format).
- **`SyncWriter`** — filesystem apply helpers (symlink-or-copy,
  guideline-block write, stale-skill removal) extracted from
  `SyncCommand` to keep the command class focused on
  orchestration.
- Plan → render → apply pipeline. Planning is pure; rendering
  and applying are independent passes driven by the plan.

## Fixes

- `array_any()` (PHP 8.4-only) usage replaced with a
  collection-based drift check — was failing the CI matrix's
  PHP 8.2 / 8.3 cells before the fix landed.
- `storage/logs/` now gitignored; a test-generated `laravel.log`
  had been accidentally committed in a prior release and
  un-tracked.

## Deferred

- **Content-drift detection on copied (non-symlink) skills** —
  spec tracked as `specs/0.7.0/copied-skill-content-drift.md`.
- **Composer `post-autoload-dump` auto-sync** — spec
  `specs/0.6.0/composer-auto-sync-hook.md`, will land before 0.6.x
  closes.
- **Category / ActionKind / SkipReason enums** — stringly-typed
  tokens remain across `SyncCommand` / `SyncFormatter` /
  `SyncReporter`. Candidate for a 0.7.x cleanup spec; review
  flagged but out of scope.

## Compatibility

No breaking changes. Text format byte-compatible with 0.5.0. JSON
is additive. Config schema unchanged. Command surface gains the
`--format=text|json` option (default `text`, preserving prior
behaviour).

## 0.5.0 - 2026-04-19

Shipped content release — driven by real feedback from two downstream
peers (`laravel-fluent-validation`, `laravel-js-store`) running
package-boost 0.3–0.4 in anger. Two new skills land, the foundation
stops assuming Pest-first, and Boost-specific commands move into
their own table so PHPUnit-only and Boost-less packages stop reading
dead copy.

## What's new

### Runner-agnostic foundation + Boost sub-table

`resources/boost/guidelines/foundation.md` and the shipped
`package-development` SKILL no longer imply Pest-first test running.
The `php artisan test` row now reads:

> The package's configured test runner
(`vendor/bin/pest` or `vendor/bin/phpunit`)

Boost-specific rows (`boost:install`, `boost:mcp`) move into a
dedicated sub-table:

```
### Commands that require `laravel/boost`

| Instead of | Use |
|---|---|
| `php artisan boost:install` | `vendor/bin/testbench boost:install` |
| `php artisan boost:mcp`     | `vendor/bin/testbench boost:mcp`     |



```
Readers without Boost installed see the heading, understand the rows
don't apply, and skip. PHPUnit-only packages stop reading Pest-first
ordering as advice.

### New shipped skill: `cross-version-laravel-support`

**Preventive** workflow for packages supporting multiple Laravel / PHP
majors. Activate before writing version-sensitive code. Covers:

- Reading `composer.json` constraints (`^11.0||^12.0||^13.0` decoded)
- Version-guard patterns — `version_compare(app()->version(), ...)`,
  `method_exists` feature detection, conditional trait composition
- Local verification workflow — default / `--prefer-lowest` /
  `--prefer-stable`
- Deprecation-but-not-removed trap
- Common failure modes (Blade directive availability, parameter
  defaults, macro scopes)

### New shipped skill: `ci-matrix-troubleshooting`

**Diagnostic** workflow for after a matrix cell has gone red. Covers:

- Local reproduction of the failing cell
- Usual suspects — transitive dep floor bumps
  (`roave/security-advisories`), Testbench ↔ PHPUnit interlock, wrong
  package floor, phpstan/larastan incompat, missing version guard
- Deeper diagnostics — `composer why-not`,
  `composer update ... --dry-run`
- Fix patterns — widen constraint, raise floor, exclude cell,
  `conflict` directive
- Filing useful upstream issues

Trigger partition is deliberate: the preventive and diagnostic skills
cross-link and share only the `testbench` descriptor. Vocabulary like
`prefer-lowest` / `prefer-stable` lives on the diagnostic skill only,
so the skill matcher fires predictably on "CI just broke" language.

### `.ai/` authoring schema docs

New `## Authoring guidelines` section in the shipped
`package-development` SKILL covers the four things downstream authors
kept reverse-engineering from sibling files:

- **Guideline file shape** — plain markdown, no required frontmatter,
  filename controls ordering
- **Skill file shape** — YAML frontmatter (`name`, `description`),
  body is markdown, `description` is the trigger surface
- **Rendering model** — shipped first, `---` divider, user content
  second
- **Opting out** — cross-links to the README's *Customising excluded
  guidelines* section (no duplication)

### Foundation Cross-Version section trimmed

The paragraph-long advice moved into `cross-version-laravel-support`.
Foundation now holds a pointer:

> Supporting multiple Laravel / PHP majors is routine for packages.
Activate `cross-version-laravel-support` **before** writing the
code; activate `ci-matrix-troubleshooting` **after** a matrix cell
has failed.

Single source of truth for the workflow, with a disambiguator so
readers know which skill to pick without drilling in.

## Upgrading

```bash
composer update sandermuller/package-boost
vendor/bin/testbench package-boost:sync



```
Or, in CI:

```bash
vendor/bin/testbench package-boost:sync --check



```
## Migration impact

Downstream `--check` will report drift on the first run after upgrade:

- **Guidelines** — `CLAUDE.md`, `AGENTS.md`,
  `.github/copilot-instructions.md` all pick up the runner-agnostic
  row, new sub-table, trimmed Cross-Version section.
- **Skills** — `.claude/skills/package-development/SKILL.md` and
  `.github/skills/package-development/SKILL.md` pick up the Authoring
  guidelines section + the Cross-Version trim + the table reshape.
- **New skills** — `.claude/skills/cross-version-laravel-support/`
  and `.claude/skills/ci-matrix-troubleshooting/` appear (plus their
  `.github/skills/` mirrors).

Expected one-time re-sync.

## Internals

- Tests introduce a `SHIPPED_SKILLS` constant so each new shipped
  skill gets automatic coverage via a Pest dataset-driven test.
  No more per-skill test-count drift.
- Seeded `ROADMAP.md` + `specs/` directory in the repo as the plan
  of record for 0.5.x / 0.6.x / 0.7.x work.

## Compatibility

No breaking changes. Config shape unchanged, command surface
unchanged, synced file layout unchanged. Content-only evolution of
the shipped skill and guideline text.

## 0.4.1 - 2026-04-18

Cosmetic follow-up to 0.4.0: the `MCP:` section of `package-boost:sync`
output now emits a `total: ...` summary line matching the Skills and
Guidelines sections.

## Fix

Before (0.4.0):

```
Skills:
  total: 24 unchanged
Guidelines:
  total: 3 unchanged
MCP:



```
After (0.4.1):

```
Skills:
  total: 24 unchanged
Guidelines:
  total: 3 unchanged
MCP:
  total: 1 unchanged



```
The three categories now render uniformly. `--show-unchanged` still
controls whether the per-target `= .mcp.json` line is printed
alongside the summary.

## Upgrading

```bash
composer update sandermuller/package-boost



```
No config or behavior changes beyond the output format.

## Compatibility

No breaking changes. Scripts grepping the sync output for
`total: N unchanged` will now find one extra match per clean run.

## 0.4.0 - 2026-04-18

Two major additions driven by real-world feedback from users running
package-boost against their packages: a CI drift-check mode and
per-target delta output on every sync. Also hardens `.mcp.json`
handling against malformed input.

## What's new

### `--check` mode for CI

```bash
vendor/bin/testbench package-boost:sync --check



```
Computes planned actions, writes nothing, exits non-zero if any skill,
guideline, or MCP target diverges from its source. Use in CI to catch
commits where `.ai/*` content was edited but the generated files
(`.claude/`, `.github/`, `CLAUDE.md`, `AGENTS.md`, `.mcp.json`)
weren't re-synced.

Combines with the subcommand flags:

```bash
vendor/bin/testbench package-boost:sync --check --guidelines



```
### Per-target delta output

Every sync now shows a per-target line for changes, with glyphs and
a per-category summary:

```
Skills:
  + .claude/skills/package-development
  + .github/skills/package-development
  total: 2 new, 0 unchanged

Guidelines:
  ~ CLAUDE.md (+12 lines)
  ~ AGENTS.md (+12 lines)
  ~ .github/copilot-instructions.md (+12 lines)
  total: 3 updated

MCP:
  = .mcp.json (unchanged; not listed)



```
Glyphs:

| glyph | meaning |
|---|---|
| `+` | new — target doesn't exist yet |
| `~` | updated — content or symlink target differs |
| `-` | removed — stale target no longer in sources |
| `=` | unchanged — hidden by default, counted in summary |

Skill updates annotate the new symlink target:

```
~ .claude/skills/package-development (symlink → ../../vendor/sandermuller/package-boost/resources/boost/skills/package-development)



```
Guideline updates show line-delta:

```
~ CLAUDE.md (+12 lines)



```
### `--show-unchanged` flag

```bash
vendor/bin/testbench package-boost:sync --show-unchanged



```
Default output is compact — unchanged targets are folded into
`total: ...` rather than listed per line, to avoid flooding large
guideline trees. Pass `--show-unchanged` to print every `=` entry
for debugging. Explicit flag name chosen over `-v` / `--verbose`
because Symfony already owns those for log verbosity.

### `.mcp.json` hardening

Previous versions assumed `.mcp.json` was always valid and
`mcpServers` was always an array. v0.4.0 survives:

- `null`, invalid JSON, or scalar roots (`"hello"`, `42`) — treated
  as empty config.
- `mcpServers` being a scalar — coerced to array before adding the
  `laravel-boost` entry.
- Extra user keys at any level — preserved on write.

Four regression tests guard these paths.

## Upgrading

```bash
composer update sandermuller/package-boost
vendor/bin/testbench package-boost:sync



```
Add a drift check to CI. Example GitHub Actions step:

```yaml
- name: Check package-boost sync
  run: vendor/bin/testbench package-boost:sync --check



```
## Internal refactor

- Extracted `SyncReporter` — pure functions for planning actions
  (`planSkillAction`, `planGuidelineAction`, `planMcpAction`),
  rendering glyphs, line-delta computation, and relative-path
  calculation. No side effects, fully unit-testable.
- Extracted `SyncSources` — shipped-then-user directory iteration for
  skills and guidelines, plus safe `.mcp.json` reading that handles
  malformed input.
- `SyncCommand` shrunk to orchestration + IO; class cognitive
  complexity stays under the project's guardrail.

## Compatibility

No breaking changes. Existing CI jobs that run
`vendor/bin/testbench package-boost:sync` without flags continue to
work; they now produce delta output alongside the previous "Synced …"
info lines.

## Deferred

- **`--format=json`**: structured output for CI parsing. Tracked for
  a follow-up; current stdout diff is enough for most CI gates via
  exit code.
- **Content-drift detection on copied (non-symlink) skills**: requires
  recursive tree hashing; edge case for filesystems without symlink
  support. Tracked.
