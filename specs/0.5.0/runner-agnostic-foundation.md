# Runner-Agnostic Foundation Rewrite

## Overview

The shipped foundation (`resources/boost/guidelines/foundation.md`)
currently implies Pest-first ordering and lists a `boost:install` row
that's dead when Laravel Boost isn't installed. Both surfaced in peer
feedback. A template engine is overkill — the fix is to rewrite the
offending paragraphs as runner-agnostic, tool-agnostic guidance. No
code changes; just a tighter foundation.

Originally scoped as conditional rendering with a mini-Blade pass.
That path was rejected during spec review: `illuminate/view` isn't in
our dependency chain, a hand-rolled template engine is maintenance
debt, and the underlying complaints are solvable with better static
prose.

---

## 1. Current State

**File:** `resources/boost/guidelines/foundation.md`

Problem rows (lines ~31 and ~34):

```
| `php artisan test`          | `vendor/bin/pest` (or `vendor/bin/phpunit`) |
| `php artisan boost:install` | `vendor/bin/testbench boost:install`        |
```

1. "`vendor/bin/pest` (or `vendor/bin/phpunit`)" reads as Pest-first
   ordering. PHPUnit-only packages pick up the guidance with a
   mild jar.
2. The `boost:install` row is dead copy when the reader's package
   doesn't depend on `laravel/boost`. `runMcp` already warns
   "Laravel Boost is not installed — skipping MCP config" in that
   case, so the guideline row confuses new readers.

## 2. Proposed Changes

### Test-runner row

Rewrite the "php artisan test" row to be runner-agnostic:

```
| `php artisan test` | The package's configured test runner (`vendor/bin/pest` or `vendor/bin/phpunit`) |
```

Also update the "Tests Are the Specification" section's
`vendor/bin/pest` example to `the project's test runner
(vendor/bin/pest or vendor/bin/phpunit)`. Keeps the guidance neutral
regardless of which runner the host package uses.

### `boost:install` row

Keep it, but move to its own sub-table clearly labelled "when Laravel
Boost is installed":

```
Commands that require `laravel/boost` as a dev dependency:

| Instead of | Use |
|---|---|
| `php artisan boost:install` | `vendor/bin/testbench boost:install` |
| `php artisan boost:mcp`     | `vendor/bin/testbench boost:mcp`     |
```

Readers without Boost installed see the section header, understand
the rows don't apply to them, and skip. Readers with Boost installed
get the same mapping as before.

## Implementation

- [ ] Rewrite the runner row in the `vendor/bin/testbench` table to
  the runner-agnostic phrasing above.
- [ ] Split the Boost-specific rows into a separate sub-table under
  a "require `laravel/boost`" heading.
- [ ] Adjust the "Tests Are the Specification" section so the
  imperative example doesn't hardcode Pest.
- [ ] Extend the existing `ships foundation guideline even without
  a user .ai/guidelines directory` test in `tests/SyncCommandTest.php`
  to assert the new phrasings:
  - The rendered CLAUDE.md contains "configured test runner".
  - The rendered CLAUDE.md contains a "require `laravel/boost`"
    heading or similar marker for the sub-table.
- [ ] Document migration impact: anyone running
  `package-boost:sync --check` after upgrading will see drift on
  every guideline target — expected, one-time update.
- [ ] Prune the corresponding entry from `ROADMAP.md`.

### Files

| File | Change |
|------|--------|
| `resources/boost/guidelines/foundation.md` | Runner-agnostic row + Boost sub-table |
| `tests/SyncCommandTest.php` | Assert new phrasings in rendered block |
| `ROADMAP.md` | Prune this item from 0.5.0 list |

---

## Open Questions

1. **Do we keep the single "vendor/bin/testbench vs php artisan"
   table or split it into two?** Splitting clarifies which commands
   require Boost but adds visual weight. Leaning toward a single
   table with a dedicated row marker like `[requires laravel/boost]`
   rather than a sub-table. Decide during implementation.

2. **Does any other shipped content (e.g.
   `package-development/SKILL.md`) still imply Pest-first?**
   Answered: yes, SKILL.md line 14 has the same mapping. Fold the
   same rewrite into this spec's implementation task for SKILL.md.

---

## Findings

<!-- Notes added during implementation. Do not remove this section. -->
