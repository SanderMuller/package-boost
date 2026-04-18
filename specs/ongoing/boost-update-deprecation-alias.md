# `boost:update` Deprecation Alias

## Overview

Old skill bundles and documentation floating around reference a
`boost:update` command that doesn't exist — the real command has
always been `package-boost:sync`. Register a deprecated alias that
delegates to the real command but prints a migration warning, so
stale references don't silently fail.

---

## 1. Current State

`package-boost:sync` is registered by `PackageBoostServiceProvider`
via `SyncCommand` (`src/Console/SyncCommand.php`). Anyone invoking
`boost:update` gets Laravel's `Command "boost:update" is not defined`
error with no hint at the actual command name.

## 2. Proposed Changes

Register an alias command that:

1. Emits a deprecation warning pointing at `package-boost:sync`.
2. Delegates execution to `package-boost:sync` via
   `$this->call(...)`, forwarding all options.

Use delegation rather than subclassing `SyncCommand` and duplicating
its signature. Subclassing means every new option added to
`SyncCommand` (like `--format=json` in 0.6.0) requires updating both
signatures or they drift. Delegation via `$this->call` doesn't
require the alias to declare any options; Artisan forwards the raw
arguments / options from `$this->input`.

### Sketch

```php
final class UpdateCommand extends Command
{
    protected $signature = 'boost:update';

    protected $description = '[deprecated] Alias for package-boost:sync.';

    /** @var bool */
    protected $hidden = true;

    public function handle(): int
    {
        $this->components->warn(
            'boost:update is deprecated and will be removed in a future release. Use package-boost:sync instead.',
        );

        return $this->call('package-boost:sync', array_merge(
            $this->input->getArguments(),
            $this->input->getOptions(),
        ));
    }
}
```

`$hidden = true` keeps the alias out of `artisan list`; discoverable
only to users who type it directly.

## Implementation

- [ ] `src/Console/UpdateCommand.php` — **New**, as sketched above.
- [ ] Register in `PackageBoostServiceProvider::register()`
  alongside `SyncCommand`.
- [ ] Test that `artisan('boost:update')` produces the deprecation
  warning AND runs the sync (assert on a file side-effect, not on
  output redirection).
- [ ] Test that the deprecation message names `package-boost:sync`
  explicitly — regression guard for future reworders.
- [ ] Mention the alias + deprecation + removal target in the
  release notes of the version that ships this.
- [ ] Removal target: keep for **three minor releases** after the
  one that introduces it, then remove. Dev tooling adoption is
  slow; two releases was too tight. Track the removal in
  `ROADMAP.md` under a new "sunset" section.
- [ ] Prune the entry from `ROADMAP.md` ongoing list; add the
  sunset entry per the line above.

### Files

| File | Change |
|------|--------|
| `src/Console/UpdateCommand.php` | **New** — deprecated alias via delegation |
| `src/PackageBoostServiceProvider.php` | Register `UpdateCommand` |
| `tests/UpdateCommandTest.php` | **New** — warning + delegation |
| `ROADMAP.md` | Move from ongoing to sunset, with target version |

---

## Open Questions

1. **Is anyone actually invoking `boost:update`?** The peer saw it
   in a floating skill bundle, not in live usage. If telemetry
   were available we'd check. Given the tiny implementation cost
   and the documentation value, ship anyway.

2. **Does `$this->call()` route output back through the parent
   command's output streams?** Laravel's Command::call delegates
   to Artisan::call with the current BufferedOutput; the child
   command's `$this->line` / warn / error writes to the same
   stream the parent received. Verify during implementation —
   if routing is awkward, fall back to
   `Artisan::call('package-boost:sync', ...)` with explicit output
   capture.

---

## Findings

<!-- Notes added during implementation. Do not remove this section. -->
