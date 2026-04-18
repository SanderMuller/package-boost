# `package-boost:sync --format=json`

## Overview

Add machine-readable output to `package-boost:sync` so CI scripts and
pre-release automation can parse drift detection results without
regex-matching glyph lines. Requested by the fluent-validation peer;
deferred from 0.4.0 as pure additive scope.

---

## 1. JSON Schema

The top-level shape is a single JSON document printed to stdout. The
existing glyph output is replaced (not appended) when `--format=json`
is active, so piping into `jq` works without noise. All warnings and
headers go to stderr or are replaced with structured fields.

```json
{
  "schema":    1,
  "check":     true,
  "drift":     false,
  "skills": {
    "new":       [{ "target": ".claude/skills/package-development" }],
    "updated":   [{ "target": ".claude/skills/package-development",
                    "hint":   "symlink → ../../vendor/..." }],
    "removed":   [{ "target": ".claude/skills/stale-skill" }],
    "unchanged": 23
  },
  "guidelines": {
    "new":       [],
    "updated":   [{ "target": "CLAUDE.md", "line_delta": 12 }],
    "unchanged": 2
  },
  "mcp": {
    "action":  "unchanged",
    "target":  ".mcp.json"
  }
}
```

Field rules:

- `schema`: integer, currently `1`. Bumped on breaking change to this
  document.
- `drift`: logical-OR. `true` iff any `new`/`updated`/`removed` array
  in skills/guidelines is non-empty, or `mcp.action` is anything
  other than `unchanged`.
- `check`: echoes the input flag so consumers can distinguish a
  drift-only report (`--check`) from a post-write report.
- `unchanged` type — `int` by default; `array<{target}>` when
  `--show-unchanged` is passed. Callers parse with a type check, or
  use the matching CLI flag for their scenario.
- All arrays are stable-sorted by `target` for deterministic diffs.

### No-source / skipped states

When a category has no sources to process, emit a structured field
instead of a warn line:

```json
"skills": { "skipped": "no-sources" }
"mcp":    { "action":  "skipped", "reason": "laravel-boost-not-installed" }
```

`skipped` replaces the category object; other fields (`new` / `updated`
/ `removed` / `unchanged`) are absent. `drift` treats skipped as
non-drift (`false`) — skipped categories can't have drift.

## 2. CLI Contract

- `--format=text` (default) or `--format=json`. Any other value
  errors out in `handle()` before `runSkills` — Laravel's option
  parsing is stringly-typed; validation is manual.
- Plays with `--show-unchanged`: when both active, `unchanged`
  becomes an array of targets instead of a count.
- Combines with `--check`: exit code is still 0 on clean, 1 on
  drift. Independent of format.
- **stdout / stderr split** — JSON document goes to stdout,
  nothing else. Headers ("Skills:"), per-target lines, and Boost-
  missing warnings that currently use `$this->components->warn`
  must be suppressed (or rerouted to stderr) when
  `--format=json`.

## 3. Rendering Split

Today the glyph output is emitted inline by each `run*` method via
`$this->line()`. Split planning from rendering:

1. `run*` methods build a typed plan struct per category (no I/O
   to terminal; still perform source reads needed for planning).
2. `SyncCommand::handle()` collects all plans, then hands them to
   a formatter.
3. `SyncFormatter` renders once, format chosen by flag.

Also opens the door to a future `--format=github` (Actions
annotations) without touching the planner.

## Implementation

### Phase 1: Rendering split (Priority: HIGH)

- [ ] `SyncPlan` readonly value object — `new: SyncAction[]`,
  `updated: SyncAction[]`, `removed: SyncAction[]`, `unchanged:
  int`, plus optional `skipped: ?string` for the no-sources /
  Boost-missing case.
- [ ] `SyncAction` readonly value object — `target: string`,
  `hint: ?string`, `lineDelta: ?int` (null for actions where the
  concept doesn't apply; e.g. new skills).
- [ ] Refactor `SyncCommand::runSkills` / `runGuidelines` /
  `runMcp` to build and return a `SyncPlan` instead of printing
  directly. Move writes to `apply*` methods that run only when
  `!$check`.
- [ ] `SyncFormatter::renderText(SyncPlan $skills, SyncPlan
  $guidelines, SyncPlan $mcp, bool $showUnchanged): void` — reproduces
  the existing glyph output exactly. Uses `$this->line` via an
  injected output callable.
- [ ] Tests — snapshot on existing glyph text to confirm no
  regression (the `--check fails when only one category is
  drifting` test, the MCP tests, all current output assertions).

### Phase 2: JSON format (Priority: HIGH)

- [ ] `--format=text|json` option on the command signature.
- [ ] Manual validation in `handle()` — unknown values error with
  "Invalid --format value; expected text or json".
- [ ] `SyncFormatter::renderJson(...): string` — returns the JSON
  document per Section 1; caller writes to stdout.
- [ ] Route all informational output away from the JSON channel.
  Warn-class messages (Boost missing) become either stderr lines or
  are reflected in `skipped` / `reason` fields.
- [ ] Tests — schema shape, `schema: 1` present, stable sort on all
  arrays, drift calculation, `skipped` for Boost-missing MCP,
  `--show-unchanged` toggles `unchanged` between int and array,
  stdout contains only JSON, stderr receives any warnings.

### Phase 3: Documentation (Priority: MEDIUM)

- [ ] README section under "CI drift check" documenting
  `--format=json` with a `jq` example:
  ```bash
  vendor/bin/testbench package-boost:sync --check --format=json | jq '.drift'
  ```
- [ ] GitHub Actions example showing parsing the JSON to post a
  PR comment listing drifted targets (use
  `jq -r '.guidelines.updated[].target'`).
- [ ] Document migration impact: no behavior change for existing
  (`text` format) callers; the `--format=json` path is additive.
- [ ] Prune the entry from `ROADMAP.md`.

### Files

| File | Change |
|------|--------|
| `src/Console/SyncPlan.php` | **New** — per-category plan struct |
| `src/Console/SyncAction.php` | **New** — per-target action entry |
| `src/Console/SyncFormatter.php` | **New** — text + JSON renderers |
| `src/Console/SyncCommand.php` | Refactor to plan-then-render; add `--format` with manual validation |
| `tests/SyncCommandTest.php` | Snapshot existing text output; add JSON coverage |
| `tests/SyncFormatterTest.php` | **New** — renderer unit tests |
| `README.md` | Document `--format=json` |
| `ROADMAP.md` | Prune |

---

## Open Questions

1. **Where is stderr routing tested?** Pest `->expectsOutputToContain`
   only inspects stdout. Testing that warnings hit stderr requires
   capturing the Symfony Console output streams separately. The
   `artisan` Pest helper exposes `expectsOutputToContain` for
   stdout; use `OutputBufferedCommand` or a direct stream capture
   for stderr assertions. Decide during Phase 2 implementation.

2. **Does the `skipped` field break the "skills is always an object
   with N array fields" schema contract?** Yes — consumers must
   check for `skipped` before indexing. Document in the schema
   docstring. The contract is "skills is always an object; its
   shape is either `{skipped: string}` or `{new, updated, removed,
   unchanged}`." Cleaner than alternatives.

---

## Findings

<!-- Notes added during implementation. Do not remove this section. -->
