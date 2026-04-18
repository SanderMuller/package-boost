# Package Boost

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
- **Auto-discovered skill** — the `package-development` skill ships via `resources/boost/skills/` and is picked up by Boost automatically
- **App-only guidelines stripped** — defaults exclude Inertia, Livewire, Filament, Volt, Folio, Pennant, Wayfinder, Nightwatch, Pulse, Herd, Sail, Tailwind, Vite, deployments, and `laravel/style|api|localization`

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
