---
name: package-development
description: "Use when developing Laravel packages with Orchestra Testbench. Activates when: writing package code, running package tests, working with testbench, configuring service providers, or when user mentions: package, testbench, package development, service provider."
---

# Laravel Package Development

This is a Laravel **package**, not a full application. There is no `artisan`, no `app/` directory, no database by default.

## Use `vendor/bin/testbench` Instead of `artisan`

| Do NOT use | Use instead |
|---|---|
| `php artisan test` | `vendor/bin/pest` |
| `php artisan tinker` | `vendor/bin/testbench tinker` |
| `php artisan make:*` | Create files manually in `src/` |
| `php artisan boost:mcp` | `vendor/bin/testbench boost:mcp` |
| `php artisan boost:install` | `vendor/bin/testbench boost:install` |

## Source Layout

- `src/` — package source code (PSR-4 autoloaded)
- `tests/` — test suite
- `config/` — publishable config files (if any)
- `resources/` — views, translations, Boost skills
- `database/` — migrations and factories (if any)

Check `composer.json` `autoload.psr-4` for the exact namespace mapping.

## Testing

Tests run via Pest or PHPUnit with Orchestra Testbench providing the Laravel application context. The base test case is typically `Orchestra\Testbench\TestCase`.

To register the package's service provider for tests, look for `getPackageProviders()` in the test base class or `testbench.yaml` `providers`.

## Cross-Version Compatibility

**Always check `composer.json` before using version-specific features:**

- `require.php` — supported PHP versions
- `require.illuminate/*` — supported Laravel versions

When the package supports multiple Laravel versions:
- Do NOT use features exclusive to newer versions without a version guard
- Run the full test suite to catch regressions — CI tests across the full matrix
- Check sibling files for patterns used to handle version differences

## Syncing AI Skills and Guidelines

The `.ai/` directory is the source of truth for AI tooling:

```
.ai/
├── guidelines/     # Always-loaded context (*.md)
└── skills/         # On-demand skills
    └── {name}/SKILL.md
```

After editing files in `.ai/`, sync to agent directories:

```bash
vendor/bin/testbench package-boost:sync
```

This copies skills to `.claude/skills/` and `.github/skills/`, and writes guidelines into `CLAUDE.md`, `AGENTS.md`, and `.github/copilot-instructions.md`. Commit the generated files alongside the `.ai/` sources — they ship with the package.

Verify CI and local state are in sync:

```bash
vendor/bin/testbench package-boost:sync --check
```

Exits non-zero when generated files drift from sources. Use in CI to catch commits where `.ai/*` was edited but generated files weren't re-synced.
