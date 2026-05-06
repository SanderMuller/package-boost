# Laravel Package README Conventions

Apply these on top of the generic guidance in `SKILL.md`. Surveyed against 11 well-maintained packages in 2026: Spatie laravel-permission, laravel-medialibrary, laravel-backup, laravel-data, laravel-csp; barryvdh/laravel-debugbar; beyondcode/laravel-dump-server; laravel/socialite; laravel/horizon; awcodes/filament-curator; wire-elements/spotlight.

## Shape selection for Laravel packages

- **First-party Laravel packages** (`laravel/*`) overwhelmingly use the **stub** shape and defer to laravel.com. Match this if you're contributing to a first-party package or a satellite that has its own docs site.
- **Spatie packages** use **comprehensive** but offload deeper docs to spatie.be/docs. Compromise shape: enough in-README to evaluate the package, full docs elsewhere.
- **Ecosystem plugins** (Filament, Livewire, Nova plugins) tend to **comprehensive in-README** because plugin authors usually don't run a docs site. Curator (501 lines) and Spotlight (423 lines) both inline everything.

## Install snippet (canonical)

```bash
composer require vendor/package
```

Service provider auto-discovery has been the default since **Laravel 5.5** — you do not need to instruct users to register the provider in `config/app.php`. Any "add the provider to your providers array" instruction is stale.

If the package publishes config or migrations, the README install section should show the publish + migrate steps with the package's actual tag names. Spatie packages typically tag as `<short-name>-config` and `<short-name>-migrations`:

```bash
php artisan vendor:publish --tag="<short-name>-config"
php artisan vendor:publish --tag="<short-name>-migrations"
php artisan migrate
```

Replace `<short-name>` with whatever the package's `ServiceProvider::publishes()` calls actually use. Don't ship `package-name-config` literally — readers copy verbatim.

## Testing snippet

```bash
composer test
```

If the package uses Testbench directly (some Spatie packages do):

```bash
vendor/bin/testbench package:test
```

If the package uses Pest:

```bash
vendor/bin/pest
```

Don't tell users to set up a Laravel app to run package tests — Testbench is the harness, that's the whole point.

## Section ordering (comprehensive shape)

Observed pattern in Spatie/community packages:

1. **H1 title + tagline + badges row**
2. **Code-first hero example** (optional, strong pattern in Spatie)
3. **Sponsorship/support pitch** (Spatie heritage; skip if not Spatie — feels off otherwise)
4. **Installation**
5. **Configuration** (if config publishes anything)
6. **Usage** — task-oriented sections, code-first
7. **Advanced features** — one H2 per major surface
8. **Closing-matter spine** per `SKILL.md` (Testing → Changelog → Contributing → Security → Credits → License)

For ecosystem plugins crossing major boundaries (Filament v3 → v4 → v5, Livewire v2 → v3), put **Compatibility** and **Upgrading** H2s **before** Installation. Curator does this. Readers checking compatibility shouldn't have to scroll past install instructions.

## Version matrix

A real PHP × Laravel × package matrix table is **not** universal. Surveyed packages supporting Laravel 11/12/13 typically don't ship one — they use a single sentence in Installation: "Requires PHP 8.2+ and Laravel 11/12/13." Spatie packages add a "Using an older version of PHP / Laravel?" link to docs instead of a table.

Ship a real matrix table only when **crossing an ecosystem-package major boundary** — Filament v4 + v5, Livewire v2 + v3, Nova v4 + v5. Readers picking a plugin version against a host they're stuck on need this, and a sentence isn't enough:

| Package | PHP    | Filament |
|---------|--------|----------|
| 5.x     | 8.2+   | v5       |
| 4.x     | 8.1+   | v4       |

For everything else, a sentence beats a table.

## Filament plugins specifically

Filament v5 split the install dependency between two packages:

- `filament/filament` — required when your plugin needs the full panel runtime.
- `filament/support` — required when your plugin only consumes Filament internals (forms, tables, infolists) without needing the panel itself.

State which one your plugin requires in Installation. `awcodes/filament-curator` requires `filament/filament: ^5.0`; `filament/spatie-laravel-media-library-plugin` requires `filament/support`.

## Laravel-specific anti-patterns

- "Add `Vendor\Package\PackageServiceProvider::class` to `config/app.php`" — stale since Laravel 5.5.
- "Run `php artisan tinker` to test" — fine for apps; for packages, use `vendor/bin/testbench tinker`.
- "Set up a fresh Laravel app to test" — Testbench is the harness.
- Ranges on the Laravel constraint that don't match `composer.json` (e.g. README says `^11.0`, composer says `^11.0|^12.0`).

## Cross-refs (Laravel-specific)

- `lean-dist` skill — `.gitattributes` `export-ignore` for `.ai/`, `.claude/`, `.cursor/`, etc. Keeps `composer archive` and Packagist tarballs lean.
- `package-development` skill — Testbench harness, source layout, "no `php artisan` in package context" rules.
- `cross-version-laravel-support` skill — when supporting multiple Laravel majors, version-guarded code patterns.
