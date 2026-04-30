# Package Boost

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sandermuller/package-boost.svg?style=flat-square)](https://packagist.org/packages/sandermuller/package-boost)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/package-boost/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sandermuller/package-boost/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/package-boost/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/sandermuller/package-boost/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Laravel Compatibility](https://badge.laravel.cloud/badge/sandermuller/package-boost?style=flat)](https://packagist.org/packages/sandermuller/package-boost)

AI tooling for Laravel package developers. Bridges the gap between [Laravel Boost](https://laravel.com/docs/boost) (designed for applications) and package development with [Orchestra Testbench](https://packages.tools/testbench).

## What It Does

- Syncs `.ai/skills/` to per-agent skill dirs (`.claude/skills/`, `.cursor/skills/`, `.agents/skills/`, `.github/skills/`, `.junie/skills/`, `.kiro/skills/`) so 9 agents — Claude Code, Cursor, GitHub Copilot, Codex, Gemini, Junie, Kiro, OpenCode, Amp — can use them
- Syncs `.ai/guidelines/` into `CLAUDE.md`, `AGENTS.md`, and `GEMINI.md`
- Generates `.mcp.json` pointing to `vendor/bin/testbench boost:mcp` when Boost is installed
- Ships a `package-development` skill that teaches AI agents how to work with Testbench
- Ships a `lean-dist` skill that on-ramps consumers to [`stolt/lean-package-validator`](https://github.com/raphaelstolt/lean-package-validator) for `.gitattributes` hygiene, with AI-era `export-ignore` entries (`.ai`, `.claude`, `AGENTS.md`, `CLAUDE.md`, …) lpv's defaults don't cover

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

## Installation

```bash
composer require sandermuller/package-boost --dev
```

Add the service provider to your `testbench.yaml`:

```yaml
providers:
  - SanderMuller\PackageBoost\PackageBoostServiceProvider
```

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

Reports planned actions without writing. Exits non-zero if any skill, guideline, or MCP target differs from its source. Use in CI to catch "forgot to sync" commits.

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
entries from agents that fell out of the selection. These are not
auto-removed (guideline files may carry user content); delete them
manually or re-include the agent.

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
| **For**                  | Laravel applications             | Laravel packages                               |
| **Runs via**             | `php artisan`                    | `vendor/bin/testbench`                         |
| **Discovers skills**     | From app + vendor packages       | From `.ai/` + `vendor/*/resources/boost/`      |
| **Generates guidelines** | Composes from installed packages | Copies `.ai/` + merges vendor contributions    |
| **MCP server**           | Built-in                         | Delegates to Boost when installed              |

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a per-release history. Entries
are auto-generated from GitHub Releases.

## License

MIT
