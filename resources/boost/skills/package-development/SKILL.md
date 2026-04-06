---
name: package-development
description: "Use when developing Laravel packages with Orchestra Testbench. Activates when: writing package code, running package tests, working with testbench, configuring service providers, or when user mentions: package, testbench, package development, service provider."
---

# Laravel Package Development

## Context

This is a Laravel **package**, not a full Laravel application. It uses Orchestra Testbench to provide a Laravel environment for testing and development.

## Key Differences from Application Development

- **No `artisan`** — use `vendor/bin/testbench` instead of `php artisan`
- **No `app/` directory** — source code lives in `src/`, tests in `tests/`
- **No database** — unless configured in `testbench.yaml`
- **No routes, views, or controllers** — unless the package provides them
- **Cross-version support** — code must work across multiple PHP and Laravel versions

## Commands

| Instead of | Use |
|---|---|
| `php artisan test` | `vendor/bin/pest` or `vendor/bin/testbench package:test` |
| `php artisan make:*` | Create files manually following package conventions |
| `php artisan tinker` | `vendor/bin/testbench tinker` |
| `php artisan boost:mcp` | `vendor/bin/testbench boost:mcp` |

## Quality Checks

```bash
# Code style
vendor/bin/pint --dirty --format agent

# Static analysis
vendor/bin/phpstan analyse --memory-limit=2G

# Tests
vendor/bin/pest

# All quality checks (if configured)
composer qa
```

## Service Provider

Packages register functionality via a service provider. Check `composer.json` for the `extra.laravel.providers` key to find it. The service provider is auto-discovered by Laravel applications that install the package.

## Testbench Configuration

`testbench.yaml` configures the test environment:
- `providers` — additional service providers to load
- `migrations` — migration paths
- `seeders` — database seeders

## Testing

- Tests use `Orchestra\Testbench\TestCase` as the base class (or Pest with testbench plugin)
- The test environment provides a full Laravel application context
- Use factories and in-memory SQLite for database testing

## Cross-Version Compatibility

Always consider:
- **PHP versions** — check `composer.json` `require.php` constraint
- **Laravel versions** — check `illuminate/*` constraints
- Avoid using features only available in newer versions unless guarded
- CI matrix tests across all supported version combinations

## AI Skills and Guidelines

This package uses `package-boost` for AI tooling. The `.ai/` directory is the source of truth:

```
.ai/
├── guidelines/     # Project-wide AI instructions (*.md)
└── skills/         # On-demand skill definitions
    └── {name}/SKILL.md
```

After editing `.ai/` files, run `vendor/bin/testbench package-boost:sync` to publish them to `.claude/skills/`, `.github/skills/`, and guideline files.
