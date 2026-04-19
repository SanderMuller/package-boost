# Package Boost

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sandermuller/package-boost.svg?style=flat-square)](https://packagist.org/packages/sandermuller/package-boost)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/package-boost/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sandermuller/package-boost/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/package-boost/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/sandermuller/package-boost/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Laravel Compatibility](https://badge.laravel.cloud/badge/sandermuller/package-boost?style=flat)](https://packagist.org/packages/sandermuller/package-boost)

AI tooling for Laravel package developers. Bridges the gap between [Laravel Boost](https://laravel.com/docs/boost) (designed for applications) and package development with [Orchestra Testbench](https://packages.tools/testbench).

## What It Does

- Syncs `.ai/skills/` to `.claude/skills/` and `.github/skills/` so Claude Code, GitHub Copilot, and Codex can use them
- Syncs `.ai/guidelines/` into `CLAUDE.md`, `AGENTS.md`, and `.github/copilot-instructions.md`
- Generates `.mcp.json` pointing to `vendor/bin/testbench boost:mcp` when Boost is installed
- Ships a `package-development` skill that teaches AI agents how to work with Testbench

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

### 1. Create your skills and guidelines

```
.ai/
├── guidelines/
│   └── my-conventions.md
└── skills/
    └── my-skill/
        └── SKILL.md
```

### 2. Sync to agent directories

```bash
vendor/bin/testbench package-boost:sync
```

### 3. Commit the generated files

The sync copies your `.ai/` files to the directories each AI tool expects. Commit both the source (`.ai/`) and the generated files (`.claude/`, `.github/`, `CLAUDE.md`, `AGENTS.md`).

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

### Composer script

```json
{
    "scripts": {
        "sync-ai": "vendor/bin/testbench package-boost:sync"
    }
}
```

## With Laravel Boost

When `laravel/boost` is also installed as a dev dependency, you get:

- **MCP server** — `package-boost:sync --mcp` generates the correct `.mcp.json` config
- **Doc search** — Boost's `search-docs` tool works out of the box via Testbench
- **Shipped `package-development` skill** — ships via `resources/boost/skills/` and is bundled into `.claude/skills/` and `.github/skills/` by `package-boost:sync`, so downstream agents always get it regardless of Boost version.
- **Package-tuned foundation** — ships `resources/boost/guidelines/foundation.md` with package-dev framing (Testbench harness, semver, public API discipline). `package-boost:sync` bundles it into the `<package-boost-guidelines>` block ahead of any user-authored `.ai/guidelines/` content, separated by a horizontal rule.
- **App-only guidelines stripped** — defaults exclude `foundation` (Boost's app-tuned version), Inertia, Livewire, Filament, Volt, Folio, Pennant, Wayfinder, Nightwatch, Pulse, Herd, Sail, Tailwind, Vite, deployments, and `laravel/style|api|localization`

### Customising excluded guidelines

Publish the config and edit `config/package-boost.php`:

```bash
vendor/bin/testbench vendor:publish --tag=package-boost-config
```

The `excluded_boost_guidelines` array is merged into `boost.guidelines.exclude` at boot. Keys match Boost's `GuidelineComposer` keys exactly (e.g. `livewire/core`, `filament/v4`, `herd`).

## How It Differs from Laravel Boost

|                          | Laravel Boost                    | Package Boost                     |
|--------------------------|----------------------------------|-----------------------------------|
| **For**                  | Laravel applications             | Laravel packages                  |
| **Runs via**             | `php artisan`                    | `vendor/bin/testbench`            |
| **Discovers skills**     | From app + vendor packages       | From `.ai/` directory             |
| **Generates guidelines** | Composes from installed packages | Copies your markdown files        |
| **MCP server**           | Built-in                         | Delegates to Boost when installed |

## License

MIT
