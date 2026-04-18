---
name: ci-matrix-troubleshooting
description: "Use when a package's CI matrix has gone red and needs diagnosis. Activates on: ci matrix fail, matrix red, prefer-lowest fail, prefer-stable fail, dependency conflict, composer resolve, version excluded, security-advisories floor, testbench phpunit interlock, matrix cell regression, or when user mentions a specific cell failing in GitHub Actions."
---

# CI Matrix Troubleshooting

Use this skill when a matrix cell has already failed. For preventive
work — writing code that'll survive the full range — activate
`cross-version-laravel-support` instead.

## Reproduce locally first

Before reading CI logs in detail, reproduce the failing cell's
resolution locally. Most matrix failures are resolver differences,
not code bugs.

```bash
# Match the failing cell's resolution strategy
composer update --prefer-lowest --prefer-dist --no-interaction

# Run the project's configured test runner (vendor/bin/pest or vendor/bin/phpunit)
```

Swap `--prefer-lowest` for `--prefer-stable` to match the other
common cell. If the cell pins a specific PHP version, switch shells
with `phpenv` / `asdf` / Docker before running.

Always restore the default state after:

```bash
composer install
```

## Usual suspects

Failures cluster around a handful of patterns. Check in order —
cheap diagnostics first.

### 1. A transitive dep bumped its floor

Common culprit: `roave/security-advisories` tightens a PHPUnit /
Guzzle / etc. floor in a new release, and `prefer-lowest` in the
package pulls the now-incompatible combination.

Diagnose with `composer why-not`:

```bash
composer why-not phpunit/phpunit 11.0
```

Fix: widen the package's constraint to match the advisory's floor,
or add a `conflict` entry that excludes the advisory's bad range.

### 2. Testbench ↔ PHPUnit interlock

Testbench pins PHPUnit constraints per major. `prefer-lowest` can
land on a Testbench version whose PHPUnit range the matrix cell
can't reach (e.g. the cell's Laravel column needs Testbench 9,
which requires PHPUnit 10+, but another cell pins 9.x).

Fix: tighten the `orchestra/testbench` floor in `require-dev` to
exclude the incompatible version.

### 3. The package's own `require` floor is wrong

The declared minimum doesn't actually install with its dependencies
at the floor version. Happens when a code path uses a method added
in a minor release above the declared floor.

Diagnose: `prefer-lowest` fails with "method X does not exist" or
"class Y not found". Check the upstream CHANGELOG for when the
symbol appeared.

Fix: bump the package's own floor to the version where the symbol
landed. Document in the release notes.

### 4. phpstan / larastan floor incompatible with older Laravel

Static-analysis tooling often drops support for older frameworks
before runtime packages do. A `prefer-lowest` run with Laravel 11
and larastan's latest may fail static analysis without any runtime
issue.

Fix: pin `larastan/larastan` to the last version that supports the
package's Laravel floor, or exclude static analysis from the
`prefer-lowest` cells (not both — document the trade-off).

### 5. Blade directive / trait / facade available on only one side

Added code uses an API that doesn't exist on the floor. Covered
by the `cross-version-laravel-support` skill — diagnostic pattern
here is the same:

```bash
composer update --prefer-lowest
# run tests → error references the API name
```

Fix: guard the call site or raise the floor.

## Second-step diagnostics

If `prefer-lowest` reproduction doesn't surface the issue, zoom in
with `composer why` / `why-not`:

```bash
# Why is this package installed / blocked?
composer why vendor/package
composer why-not vendor/package 2.0.0
```

For resolver conflicts that span multiple packages, `composer
update vendor/package --with-dependencies --dry-run` shows what
would move without committing.

## Fix patterns

Pick the narrowest fix that keeps the supported range honest:

1. **Widen a constraint** — usually a transitive dep bump caught
   us off guard. Safe if the new range is a superset.
2. **Raise the package's floor** — if the feature is genuinely
   required, declare it. Bump to a minor version in the next
   release and note the minimum change.
3. **Exclude a matrix cell** — last resort. Adds justification in
   `.github/workflows/run-tests.yml` (comment on the excluded
   cell), open a tracking issue.
4. **`conflict` directive** — when a security advisory's floor is
   too aggressive for the supported range; excludes the bad
   version window.

## When to file upstream

Distinguish:

- **Our constraint is wrong** — fix locally, don't bother the
  dependency maintainer.
- **Dependency ships an over-eager floor** — file an upstream
  issue with: reproduction steps (composer command + output), the
  version window that's too narrow, and why your constraint range
  is correct.

A useful bug report on the offender saves other consumers the
same diagnostic trip.
