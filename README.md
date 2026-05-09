# Package Boost

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sandermuller/package-boost.svg?style=flat-square)](https://packagist.org/packages/sandermuller/package-boost)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/package-boost/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sandermuller/package-boost/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/package-boost/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/sandermuller/package-boost/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Laravel Compatibility](https://badge.laravel.cloud/badge/sandermuller/package-boost?style=flat)](https://packagist.org/packages/sandermuller/package-boost)

AI tooling sync for Composer packages — Laravel-aware, framework-agnostic supported. Bridges the gap between [Laravel Boost](https://laravel.com/docs/boost) (designed for applications) and package development with [Orchestra Testbench](https://packages.tools/testbench), and works the same way for non-Laravel Composer packages that adopt Testbench as a dev-only harness.

## What It Does

- Syncs `.ai/skills/` to per-agent skill dirs (`.claude/skills/`, `.cursor/skills/`, `.agents/skills/`, `.github/skills/`, `.junie/skills/`, `.kiro/skills/`) so 9 agents — Claude Code, Cursor, GitHub Copilot, Codex, Gemini, Junie, Kiro, OpenCode, Amp — can use them
- Syncs `.ai/guidelines/` into `CLAUDE.md`, `AGENTS.md`, and `GEMINI.md`
- Generates `.mcp.json` pointing to `vendor/bin/testbench boost:mcp` when Boost is installed
- Ships a `package-development` skill that teaches AI agents how to work with Testbench
- Ships a `cross-version-laravel-support` skill for packages that must span multiple Laravel / PHP majors — composer constraints, feature detection, version guards, Testbench compatibility
- Ships a `ci-matrix-troubleshooting` skill that diagnoses red CI matrix cells — `prefer-lowest` / `prefer-stable` resolves, security-advisories floors, Testbench / PHPUnit version interlock
- Ships a `lean-dist` skill that on-ramps consumers to [`stolt/lean-package-validator`](https://github.com/raphaelstolt/lean-package-validator) for `.gitattributes` hygiene, with AI-era `export-ignore` entries (`.ai`, `.claude`, `AGENTS.md`, `CLAUDE.md`, …) lpv's defaults don't cover
- Ships a `readme` skill that teaches the two README shapes (stub vs comprehensive), required sections, voice, and a canonical staleness-audit pattern — with a `references/laravel-package.md` layer for Laravel-package-specific conventions
- Ships a `release-notes` skill that helps maintainers draft GitHub release bodies — defaulting to GitHub's auto-generated format and overriding only when major/breaking — with Laravel-package-specific guidance for version-matrix shifts and CHANGELOG-automation interplay
- Ships an `upgrading` skill that teaches the canonical UPGRADING.md shape (reverse-chronological per-major sections, version-comment-labelled before/after code, impact tagging, stable H2 anchors that release-notes' `## Breaking changes` bullets link to) — with Laravel-package conventions for the four observed filename variants and ecosystem-plugin upgrade patterns
- Ships a `skill-authoring` skill that guards against silent skill-name collisions across host / vendor / package-boost defaults, teaches activating frontmatter, and pins the `.ai/skills/` vs `resources/boost/skills/` source-dir choice

### Agent coverage

`package-boost:sync` writes to the same paths each agent reads from, mirroring [Laravel Boost's](https://github.com/laravel/boost) `src/Install/Agents/` matrix:

| Agent          | Guidelines file | Skills dir       |
|----------------|-----------------|------------------|
| Claude Code    | `CLAUDE.md`     | `.claude/skills` |
| Cursor         | `AGENTS.md`     | `.cursor/skills` |
| GitHub Copilot | `AGENTS.md`     | `.github/skills` |
| Codex CLI      | `AGENTS.md`     | `.agents/skills` |
| Gemini CLI     | `GEMINI.md`     | `.agents/skills` |
| Junie          | `AGENTS.md`     | `.junie/skills`  |
| Kiro           | `AGENTS.md`     | `.kiro/skills`   |
| OpenCode       | `AGENTS.md`     | `.agents/skills` |
| Amp            | `AGENTS.md`     | `.agents/skills` |

`.agents/skills` is shared across Codex, Gemini, OpenCode, and Amp — sync writes there once and dedupes. Trim the list to the agents you actually use via `package-boost:install` (or set `agents` in `config/package-boost.php` directly).

> **Note on skill-dir consumption.** Today only Claude Code natively reads
> its skill dir as auto-activatable skills (each `SKILL.md` becomes a
> tool-callable agent). The other eight agents primarily consume the
> per-agent guidelines file (`AGENTS.md` / `CLAUDE.md` / `GEMINI.md`),
> which package-boost concatenates from `.ai/guidelines/` and shipped
> defaults. Their skill dirs are written for forward compatibility so
> the same `.ai/skills/` source becomes useful to each tool the moment
> it ships skill support — you don't need to re-author or re-sync.

## Installation

```bash
composer require sandermuller/package-boost --dev
```

Add the service provider to your `testbench.yaml`:

```yaml
providers:
  - SanderMuller\PackageBoost\PackageBoostServiceProvider
```

### Framework-agnostic packages

Package-boost works for any Composer package, not just Laravel
packages — Testbench is used as a dev-only command harness, not a
runtime dependency of the package being developed. If your package
doesn't already use Testbench, add it alongside package-boost:

```bash
composer require orchestra/testbench --dev
composer require sandermuller/package-boost --dev
```

Create `testbench.yaml` at the package root with the framework
sentinel and the package-boost provider — nothing else is required:

```yaml
laravel: '@testbench'

providers:
  - SanderMuller\PackageBoost\PackageBoostServiceProvider
```

Then sync:

```bash
vendor/bin/testbench package-boost:sync
```

`.mcp.json` generation is skipped automatically when `laravel/boost`
isn't installed (`mcp.action: "skipped"`, `reason:
"laravel-boost-not-installed"` in the JSON output) — guidelines and
skills sync as normal.

The verified framework-agnostic flow is `package-boost:sync` plus the
zero-config "all agents" default. The interactive
`package-boost:install` picker described under *Usage* below
persists its choice via Testbench's `workbench/` scaffolding helpers;
adopters that haven't run `vendor/bin/testbench workbench:install`
should either skip `package-boost:install` entirely (the default
already syncs every supported agent) or hand-edit
`workbench/config/package-boost.php` after a one-time
`vendor/bin/testbench vendor:publish --tag=package-boost-config`.

Some shipped skills are Laravel-specific by design
(`package-development`, `cross-version-laravel-support`,
`ci-matrix-troubleshooting`). They auto-activate only on prompts that
mention Laravel-shaped tooling and are otherwise idle, so they cost
nothing in a framework-agnostic repo.

## Usage

### 1. (Optional) Pick which agents to sync

```bash
vendor/bin/testbench package-boost:install
```

Interactive picker — defaults to "all 9" and pre-checks any agent
already detected in your project (existing `.cursor/`, `.kiro/`, etc.)
or imported from `laravel/boost`'s own `boost.json` if present. The
choice is persisted to `workbench/config/package-boost.php`.

Skip this step entirely to keep the zero-config default (all 9
agents). Override non-interactively with `--all`,
`--agents=claude_code,cursor`, or `--no-import`.

### 2. Create your skills and guidelines

```
.ai/
├── guidelines/
│   └── my-conventions.md
└── skills/
    └── my-skill/
        └── SKILL.md
```

Or scaffold them with the right frontmatter pre-filled:

```bash
vendor/bin/testbench package-boost:new skill my-skill --description="One-line auto-activation hook."
vendor/bin/testbench package-boost:new guideline my-conventions
```

`package-boost:new` rejects collisions unless you pass `--force`, and validates the name against the same kebab-case shape (`^[a-z][a-z0-9-]*$`) the frontmatter linter enforces — so a freshly scaffolded skill always passes `package-boost:doctor` without further edits.

### 3. Sync to agent directories

```bash
vendor/bin/testbench package-boost:sync
```

### 4. Commit the generated files

The sync copies your `.ai/` files to the directories each AI tool expects. Commit both the source (`.ai/`) and the generated files (`.claude/`, `.cursor/`, `.agents/`, `.github/`, `.junie/`, `.kiro/`, `CLAUDE.md`, `AGENTS.md`, `GEMINI.md`).

### Selective sync

```bash
vendor/bin/testbench package-boost:sync --skills
vendor/bin/testbench package-boost:sync --guidelines
vendor/bin/testbench package-boost:sync --mcp
```

### CI drift check

```bash
vendor/bin/testbench package-boost:sync --check
```

Reports planned actions without writing. Exits non-zero if any skill, guideline, or MCP target differs from its source — or if any host `.ai/skills/<name>/SKILL.md` is missing required frontmatter (`name`, `description`) or has a name/directory mismatch. Shipped and vendor skill issues surface as warnings only; `--check` fails on host issues so CI catches them before they ship. Use in CI to catch "forgot to sync" commits.

#### JSON output

```bash
vendor/bin/testbench package-boost:sync --check --format=json
```

Emits a structured JSON document on stdout — parseable by `jq` or programmatic consumers:

```json
{
    "schema": 1,
    "check": true,
    "drift": false,
    "skills": { "new": [], "updated": [], "removed": [], "unchanged": 6 },
    "guidelines": { "new": [], "updated": [], "removed": [], "unchanged": 3 },
    "mcp": { "action": "unchanged", "target": ".mcp.json" }
}
```

**Shape contract:**

- `skills` and `guidelines` carry `{ new, updated, removed, unchanged }`. Each non-unchanged array holds per-target entries with fields:
  - `target` (string) — always present, relative to the package root.
  - `hint` (string, optional) — advisory prose. For skills: `"symlink → <relative target>"` on `updated` actions. For guidelines: `"+N lines"` / `"-N lines"` / `"content updated"` on `updated`/`new` actions. No hint on `removed` or `unchanged`. Not a command-to-run; the fix for any drift is `package-boost:sync` without `--check`.
  - `line_delta` (int, optional, guidelines only) — integer line difference of the target file between its current state and what the sync would write. Only the `<package-boost-guidelines>` block is rewritten, so `line_delta` is effectively the synced-region delta (file content outside the block is never touched).
- `mcp` carries `{ action, target }` — always a single object, never an array. `action` is `"new"`, `"updated"`, or `"unchanged"`.
- `skipped` categories report structurally:
  - `skills` / `guidelines` when no sources are found: `{ "skipped": "no-sources" }`.
  - `mcp` when Laravel Boost isn't installed: `{ "action": "skipped", "reason": "laravel-boost-not-installed" }`.
  - `mcp` when `claude_code` is filtered out of the agent selection: `{ "action": "skipped", "reason": "claude-not-selected" }`.
- Arrays are stable-sorted by `target` for deterministic diffs across runs.

Example GitHub Actions step that fails the job and lists drifted targets:

```yaml
- name: Check package-boost sync
  run: |
      report=$(vendor/bin/testbench package-boost:sync --check --format=json || true)
      drift=$(echo "$report" | jq -r '.drift')
      if [ "$drift" = "true" ]; then
          echo "::error::package-boost sync drift detected"
          echo "$report" | jq -r '
              (.skills.new, .skills.updated, .skills.removed)[]?.target,
              (.guidelines.new, .guidelines.updated, .guidelines.removed)[]?.target,
              if .mcp.action == "new" or .mcp.action == "updated" then .mcp.target else empty end
          ' | sort -u | sed "s|^|  - |"
          exit 1
      fi
```

Pass `--show-unchanged` to turn the `unchanged` field from an int count into a full array of `{ target }` entries.

### Verbose output

```bash
vendor/bin/testbench package-boost:sync --show-unchanged
```

By default, the sync output lists only targets that changed and folds unchanged ones into the `total: ...` summary. Pass `--show-unchanged` to print a line per unchanged target as well.

### Migrating from older versions

Prior to the multi-agent rollout, package-boost wrote
`.github/copilot-instructions.md`. Upstream Boost migrated Copilot
guidelines to `AGENTS.md`, so package-boost no longer writes that
file. Sync warns whenever the legacy file is detected with our tag
block. To remove it automatically — only when the file contains
nothing but a fresh-from-sync block:

```bash
vendor/bin/testbench package-boost:sync --prune
```

`--prune` refuses if the file has user content outside the block,
or if the block has been edited / is stale relative to current
`.ai/` sources. Run a normal `package-boost:sync` first to refresh,
then `--prune` to clean up.

If you narrow `package-boost.agents` after a previous "all" sync,
sync also warns about leftover skill dirs / guideline files / mcp
entries from agents that fell out of the selection. To delete them
automatically, pass `--prune-orphans`:

```bash
vendor/bin/testbench package-boost:sync --prune-orphans
```

Skill dirs are removed wholesale (sync writes them in their
entirety). Guideline files have just the
`<package-boost-guidelines>` block stripped; the file is deleted
only when nothing but whitespace remains, so user-authored content
outside the block is preserved. `.mcp.json` has the `laravel-boost`
entry removed when `claude_code` is deselected.

### One-shot diagnostic

```bash
vendor/bin/testbench package-boost:doctor
```

Aggregates the checks otherwise scattered across `--check`,
`install`, and the legacy / orphan warnings into a single report:
configured + effective agents, sync drift counts, SKILL.md
frontmatter issues, deselected-agent orphans, vendor skill
collisions, MCP / Boost detection, the legacy Copilot file, and the
`.gitattributes` managed block. Exits non-zero on any blocking
finding; vendor / shipped frontmatter issues and skill collisions
are advisory (rendered, but exit-zero) so upstream bugs you can't
patch don't permablock CI.

```bash
vendor/bin/testbench package-boost:doctor --format=json
```

JSON variant is stable-shaped (`schema: 1`) and parseable by `jq`.

```bash
vendor/bin/testbench package-boost:doctor --fix
```

`--fix` auto-resolves the mechanically-safe categories — sync drift,
deselected-agent orphans, the legacy `.github/copilot-instructions.md`
file (when prunable), and the `.gitattributes` managed block — by
delegating to `package-boost:sync --prune --prune-orphans` and
`package-boost:lean` in one pass. Exit code is driven by the
post-fix report: zero when everything resolved, non-zero when
anything still needs human attention. Frontmatter issues block
exit only when they live under your host `.ai/skills/` *or* under
package-boost's own bundled `resources/boost/skills/` (a malformed
shipped SKILL.md is a real bug — in this repo or in your installed
version). Third-party vendor SKILL.md issues, `package-boost.agents`
typos, and skill collisions stay report-only — `--fix` cannot
resolve them and CI shouldn't be held hostage by upstream skill
bugs you don't own.

`--fix --format=json` adds a top-level `fix` object recording each
category's `attempted` (what doctor saw pre-fix) and `resolved`
(what actually changed). Refusals — for example sync declining to
prune a Copilot file with user content — surface as
`{ attempted: true, resolved: false }`, distinguishable from a noop
(`{ attempted: false, resolved: false }`).

### `.gitattributes` hygiene

```bash
vendor/bin/testbench package-boost:lean
```

Idempotently writes a managed `# >>> package-boost (managed) >>>` /
`# <<< package-boost (managed) <<<` block into `.gitattributes`
covering AI-era `export-ignore` paths (`.ai/`, `.claude/`, `.cursor/`,
`.agents/`, `.junie/`, `.kiro/`, `AGENTS.md`, `CLAUDE.md`,
`GEMINI.md`, …) so `composer archive` / Packagist `--prefer-dist`
tarballs stay lean. User-authored entries outside the marker block
are preserved verbatim. Pass `--check` to fail CI when the block
drifts.

The shipped `lean-dist` skill teaches the validation side
(`stolt/lean-package-validator`); this command handles the write
side.

### Composer script

```json
{
    "scripts": {
        "sync-ai": "vendor/bin/testbench package-boost:sync"
    }
}
```

### Composer auto-sync hook

Register `package-boost:sync` as a `post-autoload-dump` hook so
`composer install` / `update` / `dump-autoload` catch "forgot to
sync" mistakes automatically. Complements the `--check` CI gate: CI
catches stale generated files on the server, the hook catches them
on the contributor's machine.

**Strict variant (recommended)** — matches the CI contract. If
anything has drifted, the install fails and the contributor re-runs
`package-boost:sync` by hand:

```json
{
    "scripts": {
        "post-autoload-dump": [
            "@php vendor/bin/testbench package-boost:sync --check"
        ]
    }
}
```

**Auto-fix variant** — friendlier but mutates the working tree when
drift exists, which leaves uncommitted changes behind after a fresh
`composer install` on a dirty branch:

```json
{
    "scripts": {
        "post-autoload-dump": [
            "@php vendor/bin/testbench package-boost:sync"
        ]
    }
}
```

**Boost-less packages** — if the host doesn't depend on
`laravel/boost`, narrow the hook to skip MCP (otherwise it emits a
"Laravel Boost is not installed" warn on every composer run):

```json
{
    "scripts": {
        "post-autoload-dump": [
            "@php vendor/bin/testbench package-boost:sync --check --skills --guidelines"
        ]
    }
}
```

The hook runs via `/bin/sh` on posix and `cmd.exe` on Windows;
single-command-per-array-entry form works on both. Chained shell
operators (`&&`, `||`) are not portable across composer's shell
layers — use separate array entries if you need multiple steps.

## With Laravel Boost

When `laravel/boost` is also installed as a dev dependency, you get:

- **MCP server** — `package-boost:sync --mcp` generates the correct `.mcp.json` config
- **Doc search** — Boost's `search-docs` tool works out of the box via Testbench
- **Shipped `package-development` skill** — ships via `resources/boost/skills/` and is bundled into every selected agent's skill dir by `package-boost:sync`, so downstream agents always get it regardless of Boost version.
- **Package-tuned foundation** — ships `resources/boost/guidelines/foundation.md` with package-dev framing (Testbench harness, semver, public API discipline). `package-boost:sync` bundles it into the `<package-boost-guidelines>` block ahead of any user-authored `.ai/guidelines/` content, separated by a horizontal rule.
- **App-only guidelines stripped** — defaults exclude `foundation` (Boost's app-tuned version), Inertia, Livewire, Filament, Volt, Folio, Pennant, Wayfinder, Nightwatch, Pulse, Herd, Sail, Tailwind, Vite, deployments, and `laravel/style|api|localization`

### Customising excluded guidelines

Publish the config and edit `config/package-boost.php`:

```bash
vendor/bin/testbench vendor:publish --tag=package-boost-config
```

The `excluded_boost_guidelines` array is merged into `boost.guidelines.exclude` at boot. Keys match Boost's `GuidelineComposer` keys exactly (e.g. `livewire/core`, `filament/v4`, `herd`).

### Vendor-contributed skills and guidelines

Installed Composer packages that ship
`resources/boost/skills/<name>/SKILL.md` or
`resources/boost/guidelines/*.md` are picked up automatically and
merged into the sync. Load order:

1. Package-boost's shipped defaults
2. Vendor packages (alphabetical by `vendor/name`)
3. Host `.ai/`

For **skills**, later entries override earlier ones on name
collisions — host `.ai/skills/<name>` always wins over a vendor
contribution of the same name. For **guidelines**, each source
contributes its own block and they concatenate in load order,
separated by `---`.

Disable discovery entirely or skip specific packages via
`config/package-boost.php`:

```php
'discover_vendor_packages' => true,

'excluded_vendor_packages' => [
    'sandermuller/package-boost',
],
```

## How It Differs from Laravel Boost

|                          | Laravel Boost                    | Package Boost                                  |
|--------------------------|----------------------------------|------------------------------------------------|
| **For**                  | Laravel applications             | Composer packages (Laravel-aware, framework-agnostic supported) |
| **Runs via**             | `php artisan`                    | `vendor/bin/testbench`                         |
| **Discovers skills**     | From app + vendor packages       | From `.ai/` + `vendor/*/resources/boost/`      |
| **Generates guidelines** | Composes from installed packages | Copies `.ai/` + merges vendor contributions    |
| **MCP server**           | Built-in                         | Delegates to Boost when installed              |

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a per-release history. Entries
are auto-generated from GitHub Releases.

## License

MIT
