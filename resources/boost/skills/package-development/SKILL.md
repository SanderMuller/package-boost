---
name: package-development
description: "Use when developing Laravel packages with Orchestra Testbench. Activates when: writing package code, running package tests, working with testbench, configuring service providers, or when user mentions: package, testbench, package development, service provider."
---

# Laravel Package Development

This is a Laravel **package**, not a full application. There is no `artisan`, no `app/` directory, no database by default.

## Use `vendor/bin/testbench` Instead of `artisan`

| Do NOT use | Use instead |
|---|---|
| `php artisan test` | The package's configured test runner (`vendor/bin/pest` or `vendor/bin/phpunit`) |
| `php artisan tinker` | `vendor/bin/testbench tinker` |
| `php artisan make:*` | Create files manually in `src/` |

### Commands that require `laravel/boost`

Skip these rows if the package doesn't depend on `laravel/boost`.

| Do NOT use | Use instead |
|---|---|
| `php artisan boost:install` | `vendor/bin/testbench boost:install` |
| `php artisan boost:mcp` | `vendor/bin/testbench boost:mcp` |

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

Activate the `cross-version-laravel-support` skill when writing or
reviewing version-sensitive code. `ci-matrix-troubleshooting` covers
the workflow once a matrix cell has gone red.

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

## Authoring guidelines

Quick reference for adding content under `.ai/`.

### Guideline file shape

- Plain markdown in `.ai/guidelines/*.md`. **No frontmatter
  required.**
- One topic per file; filename controls ordering
  (`sortByName` across the sources dir).
- Files are concatenated into the `<package-boost-guidelines>` block
  in `CLAUDE.md` / `AGENTS.md` / `.github/copilot-instructions.md`.

### Rendering model

Inside the block, package-boost's shipped foundation renders first,
then a `---` horizontal rule separator, then the host package's own
`.ai/guidelines/*.md` in filename order. Only one source present?
Divider is omitted.

### Opting out of shipped content

Add the unwanted guideline's key to `excluded_boost_guidelines` in
the published `config/package-boost.php`. Keys match Boost's
`GuidelineComposer` convention (`foundation`, `livewire/core`, etc.).
See the README's _Customising excluded guidelines_ section for the
publish command and config path.

### Skill file shape

- `.ai/skills/{name}/SKILL.md` — one directory per skill.
- YAML frontmatter with `name` and `description` (required); body is
  markdown.
- `description` is the trigger surface: list the natural-language
  phrases that should activate the skill. Claude Code's matcher
  scores against this text, so be explicit about intent.
