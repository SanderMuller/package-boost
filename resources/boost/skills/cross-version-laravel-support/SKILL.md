---
name: cross-version-laravel-support
description: "Use when writing or reviewing package code that must work across multiple Laravel or PHP majors. Activates when adding a dependency, using a framework API whose availability spans the range, reading composer constraints, writing version-guarded code, or when user mentions: composer constraint, illuminate/support version, laravel version matrix, backwards compatibility, version guard, feature detection, minimum laravel version, support older laravel, prefer-lowest, prefer-stable, testbench."
---

# Cross-Version Laravel Support

Packages typically support a constraint range (e.g. `^11.0||^12.0||^13.0`).
Every public API the package touches must exist at the minimum of that
range, or be behind a version guard. Activate this skill **before**
reaching for a framework feature, not after CI goes red — see
`ci-matrix-troubleshooting` for the red-CI workflow.

## Reading `composer.json` constraints

`composer.json`'s `require` block is the source of truth. Read it
first; never rely on what's installed in `vendor/` (that's a single
resolution, not the range).

```json
"require": {
    "php": "^8.2",
    "illuminate/support": "^11.0||^12.0||^13.0"
}
```

Decode:

- `php ^8.2` — supports PHP 8.2, 8.3, 8.4. Must work on 8.2.0+
  APIs.
- `illuminate/support ^11.0||^12.0||^13.0` — Laravel 11.x, 12.x,
  13.x. Features landed after 13.0 are fair game; anything
  exclusive to 12+ needs a guard.

## Version-guard patterns

### Runtime version check

```php
if (version_compare(app()->version(), '12.0', '>=')) {
    // Laravel 12+ API
} else {
    // Laravel 11 fallback
}
```

Use when the feature is a method/function call that exists only
in newer versions.

### Feature detection

```php
if (method_exists(Collection::class, 'newMethod')) {
    // ...
}
```

Prefer feature detection over version comparison when possible —
survives version bumps without code changes.

### Conditional trait composition

```php
$traits = [HasAttributes::class];

if (interface_exists(\Illuminate\Contracts\NewContract::class)) {
    $traits[] = NewContractSupport::class;
}
```

Useful when a framework interface only exists in one half of the
range.

## Local verification workflow

Run the full suite under both extremes before pushing a
version-sensitive change:

```bash
composer update --prefer-lowest --prefer-dist --no-interaction
# run the project's configured test runner (vendor/bin/pest or vendor/bin/phpunit)

composer update --prefer-stable --prefer-dist --no-interaction
# same
```

`--prefer-lowest` resolves every dependency to its floor, catching
"works on my machine" bugs from transitive drift. `--prefer-stable`
resolves to current latest stable, matching production.

Restore the default lockfile after:

```bash
composer install
```

## Deprecation-but-not-removed trap

Laravel deprecates APIs with `@deprecated` well before removal. A
method marked deprecated in 12.x still works in 12.x — the deprecation
is a signal, not a breakage. Don't rush to replace it unless the
package's minimum version has moved past the removal version.

Check the upstream CHANGELOG before assuming a deprecation is a
blocker.

## Common failure modes

- Adding a Blade directive or facade that doesn't exist on the floor
  version — tests pass locally (latest Laravel), fail on
  `prefer-lowest`.
- Using a parameter default in a framework method that was added in
  a minor release — silent behaviour change across versions.
- Relying on a macro registered by another package whose version
  range doesn't intersect the package's full range.

## When CI actually goes red

This skill is preventive. If a CI matrix cell has already failed,
activate `ci-matrix-troubleshooting` instead — it covers the
diagnostic workflow (reproduce locally, `composer why-not`, usual
suspects, fix patterns) for existing breakage.
