# Package Boost

AI-assisted development tooling for Laravel package developers.

Syncs `.ai/skills/` and `.ai/guidelines/` to agent directories (`.claude/skills/`, `.github/skills/`, `CLAUDE.md`, `AGENTS.md`) so AI tools like Claude Code, GitHub Copilot, and Codex can use them during package development.

Also ships a `package-development` skill via Laravel Boost, teaching AI agents how to work with Testbench, package structure, and cross-version compatibility.

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

Create your skills in `.ai/skills/` and guidelines in `.ai/guidelines/`, then sync:

```bash
vendor/bin/testbench package-boost:sync
```

This copies skills to `.claude/skills/` and `.github/skills/`, and writes guidelines into `CLAUDE.md`, `AGENTS.md`, and `.github/copilot-instructions.md`.

### Options

```bash
# Sync only skills
vendor/bin/testbench package-boost:sync --skills

# Sync only guidelines
vendor/bin/testbench package-boost:sync --guidelines

# Sync only MCP config
vendor/bin/testbench package-boost:sync --mcp
```

### Composer script

Add to your `composer.json` for convenience:

```json
{
    "scripts": {
        "sync-ai": "vendor/bin/testbench package-boost:sync"
    }
}
```

## Laravel Boost integration

When `laravel/boost` is also installed, `package-boost:sync --mcp` generates the correct MCP config pointing to `vendor/bin/testbench boost:mcp`.

The package also ships a `package-development` skill via `resources/boost/skills/` that Boost auto-discovers, teaching AI agents about Testbench workflows and package conventions.

## License

MIT
