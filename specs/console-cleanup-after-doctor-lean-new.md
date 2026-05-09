# Console Cleanup After Doctor / Lean / New Additions

## Overview

The `doctor`, `lean`, and `new` commands landed alongside `sync` and
`install` without a follow-up consolidation pass — five copies of a
one-line `resolvePackageRoot()` wrapper, two byte-identical `--format`
validators, three identical config-agents narrowing blocks, and one
genuinely-divergent host-frontmatter blocking rule between `Sync` and
`Doctor`. This spec collapses the mechanical duplication, raises the
divergence to an explicit decision, and closes the prompt-path
coverage gap on `InstallCommand` (currently 63.6%).

---

## 1. Current State

### 1.1 `resolvePackageRoot()` wrapper, ×5

Each console command carries a private one-line wrapper that just
delegates to `Internal\PackageRoot::resolve()`:

| File | Line |
|---|---|
| `src/Console/SyncCommand.php` | 329 |
| `src/Console/DoctorCommand.php` | 445 |
| `src/Console/InstallCommand.php` | 215 |
| `src/Console/LeanCommand.php` | 105 |
| `src/Console/NewCommand.php` | 109 |

`PackageRoot::resolve()` is already `final` + `static` and lives in
`src/Console/Internal/PackageRoot.php:13`. The wrapper adds zero
behaviour.

### 1.2 `--format` validation duplicated, ×2

Byte-identical 8-line block at `SyncCommand:107-114` and
`DoctorCommand:39-46`:

```php
$formatOption = $this->option('format');
$format = is_string($formatOption) ? $formatOption : 'text';

if (! in_array($format, ['text', 'json'], true)) {
    $this->components->error("Invalid --format value '{$format}'; expected 'text' or 'json'.");

    return self::FAILURE;
}
```

Two callers today, but the valid set (`['text', 'json']`) is the
single source of drift if a third format is added later.

### 1.3 Config-agents narrowing duplicated, ×3 (+ 1 partial)

The pattern `is_array($configured) ? array_values(array_filter($configured, is_string(...))) : null`
appears verbatim three times:

| File | Line | Use |
|---|---|---|
| `src/Console/SyncCommand.php` | 53-56 | `selectedAgents()` cache |
| `src/Console/SyncCommand.php` | 347-353 | `warnAboutUnknownAgents()` |
| `src/Console/DoctorCommand.php` | 155-158 | `buildReport()` |

`InstallCommand.php:98-105` uses the same narrowing as the **first
step** of a fallback chain (`config → boostImport → detectInstalled`),
so it differs structurally — it short-circuits only when `$names !== []`.

### 1.4 Host-frontmatter blocking rule divergence

`SyncCommand::hasHostFrontmatterIssues` (`SyncCommand.php:229-240`)
blocks `--check` only on issues under the host's `.ai/skills/`:

```php
$hostPrefix = $this->resolvePackageRoot() . DIRECTORY_SEPARATOR
    . '.ai' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR;
```

`DoctorCommand::filterHostFrontmatter` (`DoctorCommand.php:208-226`)
blocks on host `.ai/skills/` **and** the package's own
`SyncSources::shippedDir('skills')`:

```php
$hostPrefix = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR;
$packageShippedPrefix = SyncSources::shippedDir('skills') . DIRECTORY_SEPARATOR;
```

`SyncSources::shippedDir()` resolves package-relative — so:

- **Dogfooding context (this repo):** `shippedDir` points at
  `package-boost/resources/boost/skills/` → operator-owned, blocking
  is correct.
- **Host context (consumer repo):** `shippedDir` resolves to
  `vendor/sander-muller/package-boost/resources/boost/skills/` →
  vendor-owned, blocking is **wrong** (operator can't fix it).

The earlier `doctor --fix` spec
(`specs/doctor-fix-autoremediation.md:200-205`) noted the divergence
and added a `hostFrontmatterIssues` field to `DoctorReport` so
`hasIssues()` checks the host subset only. The `filterHostFrontmatter`
logic that produces that subset still uses both prefixes — i.e.
`DoctorReport::hasIssues()` blocks on shipped issues today, contrary
to the spec's stated intent.

This is the only item in this cleanup that is **not** mechanical.

### 1.5 `InstallCommand` test coverage 63.6%

`tests/InstallCommandTest.php` (13 tests) does not exercise:

- `resolveDefaults()` (`InstallCommand.php:96-118`)
- `boostImport()` (uncovered range from coverage report)
- `detectInstalledAgents()` (uncovered range from coverage report)
- the interactive `multiselect` path (`InstallCommand.php:62-150`
  region)

Zero tests use Laravel Prompts' `Prompts::fake([...])`. The non-
interactive path is well-covered; the human-in-the-loop path is not.

---

## 2. Proposed Changes

### 2.1 Inline `resolvePackageRoot()` (Item 1)

Delete the five wrapper methods. Replace each `$this->resolvePackageRoot()`
call with `PackageRoot::resolve()`. Existing `use SanderMuller\PackageBoost\Console\Internal\PackageRoot;`
imports stay (they're already there for the wrapper to delegate to).

### 2.2 Extract `Internal\FormatOption` (Item 2)

New helper `src/Console/Internal/FormatOption.php`:

```php
<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console\Internal;

use Illuminate\Console\Command;

/**
 * @internal Resolve and validate the `--format` option shared by
 * `package-boost:sync` and `package-boost:doctor`. Returns the
 * validated format string, or `Command::FAILURE` once an error has
 * been printed via `$command->components->error(...)`.
 */
final class FormatOption
{
    public const SUPPORTED = ['text', 'json'];

    public static function resolve(Command $command): string|int
    {
        $value = $command->option('format');
        $format = is_string($value) ? $value : 'text';

        if (! in_array($format, self::SUPPORTED, true)) {
            $command->components->error(
                "Invalid --format value '{$format}'; expected 'text' or 'json'."
            );

            return Command::FAILURE;
        }

        return $format;
    }
}
```

Call sites in `SyncCommand::handle()` and `DoctorCommand::handle()`:

```php
$format = FormatOption::resolve($this);
if (is_int($format)) {
    return $format;
}
```

### 2.3 Extract `Registry::configuredNames()` (Item 3)

New static on `src/Agents/Registry.php`:

```php
/**
 * Read `config('package-boost.agents')` and narrow it to a clean
 * `array<int, string>` (or `null` when unset / not an array).
 * Centralises the `is_array → array_filter(is_string)` shape
 * shared by `Sync`, `Doctor`, and (as the first step of its
 * fallback chain) `Install`.
 *
 * @return ?array<int, string>
 */
public static function configuredNames(): ?array
{
    $configured = config('package-boost.agents');

    return is_array($configured)
        ? array_values(array_filter($configured, is_string(...)))
        : null;
}
```

Update three identical call sites (`SyncCommand:53-56`,
`SyncCommand:347-353`, `DoctorCommand:155-158`) to call
`Registry::configuredNames()`.

`InstallCommand::resolveDefaults()` uses the helper as the first step,
keeping its non-empty short-circuit + boostImport/detect fallback:

```php
$names = Registry::configuredNames();
if ($names !== null && $names !== []) {
    return $names;
}
// ... existing boostImport / detectInstalledAgents fallback
```

### 2.4 Resolve + unify host-frontmatter blocking (Item 4)

**Open question** (see Open Questions §1): should the package's own
shipped `resources/boost/skills/` block frontmatter checks?

Both candidate answers:

- **(a) Host-only (align Doctor with Sync).** Vendor-context Doctor
  becomes coherent — operators only ever fail on stuff they own.
  Dogfooding loses the safety net for our own shipped skills, but
  CI on this repo runs Sync (which would still catch host issues)
  and a separate static check could lint shipped frontmatter
  directly.
- **(b) Host + own-shipped, context-aware.** `shippedDir()` would
  need to learn whether it's resolving inside the package or in a
  vendor path — non-trivial. Probably wrong.
- **(c) Doctor stays as-is, Sync widens to match.** Symmetrical, but
  inherits (b)'s vendor-context bug into Sync as well.

After resolution, unify into `SkillFrontmatter::filterBlocking()`:

```php
/**
 * @param  array<int, array{name: string, path: string, problems: array<int, string>}>  $issues
 * @return array<int, array{name: string, path: string, problems: array<int, string>}>
 */
public static function filterBlocking(array $issues, string $root): array
```

Both `SyncCommand::hasHostFrontmatterIssues` and
`DoctorCommand::filterHostFrontmatter` call this. The duplicate
`hostPrefix` literal at `SyncCommand:231` collapses into the helper.

### 2.5 Cover `InstallCommand` prompt path (Item 5)

Use Laravel Prompts' `Prompts::fake()` (already used elsewhere in
Laravel test suites; no new dep — `laravel/prompts` ships with the
Testbench Laravel range we support). New tests in
`tests/InstallCommandTest.php`:

1. **`resolveDefaults` honours `config('package-boost.agents')` when
   non-empty** — fakes prompt, asserts the multiselect default list
   matches config.
2. **`resolveDefaults` falls back to `boostImport` when config is
   empty** — fakes prompt, stubs Boost's `installed-agents` JSON in a
   tmp host root, asserts default list matches Boost.
3. **`resolveDefaults` falls back to `detectInstalledAgents` when
   neither config nor Boost is present** — fakes prompt, seeds host
   with `.cursor/` + `CLAUDE.md`, asserts those two are pre-selected.
4. **Empty selection aborts cleanly** — fake returns `[]`, command
   exits non-zero with the "no agents selected" message.

Target: lift `InstallCommand` line coverage from 63.6% → ≥ 90%.

---

## Implementation

### Phase 1: Mechanical dedup (Priority: HIGH)

- [x] Delete `resolvePackageRoot()` from `SyncCommand`, `DoctorCommand`,
  `InstallCommand`, `LeanCommand`, `NewCommand`. Replace call sites with
  `PackageRoot::resolve()`.
- [x] Extract `src/Console/Internal/FormatOption.php`. Update
  `SyncCommand::handle()` and `DoctorCommand::handle()` to use it.
- [x] Add `Registry::configuredNames()`. Update the three identical
  call sites in `SyncCommand` and `DoctorCommand`. Update
  `InstallCommand::resolveDefaults()` to use it as the first step of
  its fallback chain.
- [x] Tests — full suite green (184 passed). No reflection-based tests
  on the wrapper methods existed. `--format` test (`SyncCommandTest.php:536`)
  still passes via the helper. `Registry::configuredNames()` exercised
  indirectly by all `--format`-and-agent tests; no dedicated unit test
  added (helper is a thin narrowing — under-test risk is low).

### Phase 2: `InstallCommand` prompt-path coverage (Priority: HIGH)

- [ ] Add `Prompts::fake([...])` setup helper to
  `tests/InstallCommandTest.php`.
- [ ] Add tests 1-4 from §2.5.
- [ ] Tests — verify line coverage on `InstallCommand` ≥ 90% via
  `vendor/bin/pest --coverage --min=...` or the existing coverage
  reporter.

### Phase 3: Host-frontmatter blocking unification (Priority: MEDIUM, blocks on Open Question 1)

- [x] Resolve Open Question 1 (host-only vs host+shipped) — see
  Resolved Questions §1.
- [x] Add `SkillFrontmatter::filterBlocking(array $issues, string $root): array`
  encoding the host-only rule.
- [x] Replace `SyncCommand::hasHostFrontmatterIssues` body with a
  `SkillFrontmatter::filterBlocking(...) !== []` check.
- [x] Replace `DoctorCommand::filterHostFrontmatter` (deleted) with a
  direct call to the same helper from `buildReport`.
- [x] Tests — added three unit tests on `SkillFrontmatter::filterBlocking`
  in `tests/SkillFrontmatterTest.php` (host blocks, shipped non-blocks,
  vendor non-blocks). Removed the now-obsolete dogfood integration test
  in `tests/DoctorCommandTest.php` that asserted shipped frontmatter
  blocks doctor exit (contradicts the resolved rule).

---

## Open Questions

None.

---

## Resolved Questions

1. **Should the package's own shipped `resources/boost/skills/` block
   frontmatter checks?** **Decision:** **(a) Host-only everywhere.**
   `SkillFrontmatter::filterBlocking` matches against the host
   `.ai/skills/` prefix only; shipped and third-party vendor issues
   surface as warnings (still in `frontmatter_issues`) but do not
   flip the exit code. **Rationale:** the previous Doctor behaviour
   (block on host + `SyncSources::shippedDir`) was vendor-incoherent —
   `shippedDir()` resolves to `vendor/sandermuller/package-boost/...`
   when run from a downstream consumer, so a malformed bundled
   SKILL.md would have permablocked the consumer's CI on something
   they can't fix. Host-only matches Sync's existing rule and is the
   semantically clean choice. The dogfood safety net is acceptable
   collateral — if it becomes load-bearing, add a separate lint step
   in this repo's CI that calls `SkillFrontmatter::lint()` directly.

---

## Findings

- The `--format` helper deliberately stops short of rendering the
  error itself: `Command::components` is `protected`, so a static
  helper can't reach the styled `error()` factory without either
  re-instantiating `Illuminate\Console\View\Components\Factory` or
  dropping back to the un-styled `Command::error()`. Caller-side
  rendering keeps the `   ERROR  ` block styling and matches existing
  test assertions (`SyncCommandTest.php:536` checks message text only,
  not the prefix).
- Phase 3 required deleting one existing dogfood test
  (`tests/DoctorCommandTest.php`'s "still blocks on malformed
  frontmatter in this package's own resources/boost/skills/") because
  it asserted the now-removed shipped-blocking behaviour. Coverage of
  the new rule moved to three unit tests on
  `SkillFrontmatter::filterBlocking` in `tests/SkillFrontmatterTest.php`.
- Sequential test runs are clean (184 passed). Parallel runs (`pest
  --parallel`) showed pre-existing test pollution unrelated to this
  cleanup — multiple tests touch shared package paths
  (`.claude/skills/`, `CLAUDE.md`, etc.) without isolation. Out of
  scope for this spec; flag as a separate item.
- Phase 2 (InstallCommand prompt-path coverage) deferred — not
  attempted in this pass.
- Codex adversarial review flagged the absence of a replacement
  blocking gate after option (a) removed shipped-frontmatter from
  doctor exit. Closed by adding a dogfood lint test in
  `tests/SkillFrontmatterTest.php` ("dogfood: this package's own
  shipped resources/boost/skills/ pass lint") that runs
  `SkillFrontmatter::lint()` directly over `package_path('resources/boost/skills/*')`
  and asserts zero issues. Catches malformed bundled SKILL.md files
  before they ship without resurrecting the doctor-exit dependency.
  185 passed (up from 184).
