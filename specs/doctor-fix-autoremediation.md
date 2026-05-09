# Doctor `--fix` Autoremediation

## Overview

Add a `--fix` flag to `package-boost:doctor` that auto-resolves the
mechanically-safe drift it currently only reports. Today operators run
`doctor`, read the output, then manually invoke `sync --prune
--prune-orphans` and `lean` to clear the issues — `--fix` collapses
that into one command. Categories that need a human decision
(`unknownAgents`, `frontmatterIssues`, `skillCollisions`) stay
report-only.

---

## 1. Current State

`DoctorCommand` (`src/Console/DoctorCommand.php:26-305`) is read-only.
It builds a `DoctorReport` (`src/Console/Internal/DoctorReport.php`)
and exits non-zero when `hasIssues()` returns true. The eight checks
mapped against fix-eligibility:

| # | Field | Source | Fix-eligible? | Existing remediation |
|---|---|---|---|---|
| 1 | `unknownAgents` | bad name in `package-boost.agents` config | **No** — needs human (typo vs pending removal) | — |
| 2 | `drift.skills > 0` | `SyncPlanner::planSkills` | **Yes** | `package-boost:sync` |
| 3 | `drift.guidelines > 0` | `SyncPlanner::planGuidelines` | **Yes** | `package-boost:sync` |
| 4 | `drift.mcp` ∈ {`new`,`updated`} | `SyncReporter::planMcpAction` | **Yes** | `package-boost:sync` |
| 5 | `frontmatterIssues` | `SkillFrontmatter::lint` | **No** — semantic SKILL.md content. Doctor's current `hasIssues()` fails on **any** issue (host, vendor, or shipped); `SyncCommand::hasHostFrontmatterIssues` (`src/Console/SyncCommand.php:229-242`) only fails on host. This spec aligns doctor with sync — see §7. | — |
| 6 | `orphans` | `DeselectedAgentArtifacts::locate` | **Yes** | `sync --prune-orphans` |
| 7 | `legacyCopilotInstructions` | `LegacyCopilotInstructions::pathFor` | **Yes** (refuses if user content) | `sync --prune` |
| 8 | `gitAttributes` (missing or stale block) | `LeanCommand::renderUpdated` | **Yes** | `package-boost:lean` |
| 9 | `skillCollisions` | `SyncSources::traceSkills` | **No (by design)** — host/vendor override is a feature; advisory exit-zero | — |

`SyncCommand` already handles five fix-eligible categories with safety
guards in place (`pruneLegacyCopilotInstructions` refuses if the file
holds user content; `pruneDeselectedAgentArtifacts` strips the
package-boost block before deleting a guideline file).
`LeanCommand::renderUpdated` is a pure transform that preserves
user-authored entries outside the marker block.

`DoctorReport::hasIssues()` already excludes `skillCollisions` and the
benign MCP statuses (`laravel-boost-not-installed`,
`claude-not-selected`) — `--fix` reuses that contract.

---

## 2. CLI Surface

New flag on `DoctorCommand::$signature`:

```
{--fix : Auto-resolve mechanically-safe drift (re-sync, prune orphans, prune legacy Copilot file, rewrite .gitattributes block)}
```

`DoctorCommand` has no `--check` flag today (`doctor` is read-only by
default, so `--check` would be redundant); no mutex needed at v1. If
`--check` is later added (open question), `--fix --check` should be
rejected.

`--format=json` + `--fix` emits the **post-fix** report only. Schema
gains an additive top-level `fix` object — see §4.

`--fix` exit code is driven **solely by the rebuilt post-fix
`DoctorReport`**, not by inner-command exit codes. `SyncCommand`
emits warnings on prune refusal but still returns `SUCCESS`
(`SyncCommand::pruneLegacyCopilotInstructions` warns and returns;
the command's `finalExit` only fails on `--check` drift). Treating
inner exits as authoritative would silently miss partial
remediation — especially in JSON mode where inner output is
suppressed.

Exit semantics:

- `0` — post-fix `DoctorReport::hasIssues()` returns `false`
  (skill collisions are already advisory by design).
- `1` — post-fix report still has any issue: drift the prune
  refused, persistent host frontmatter issues, persistent
  `unknownAgents`, etc.

---

## 3. Delegation Strategy

`--fix` delegates to existing commands rather than reimplementing
remediation. **Inner commands always run in their default (text)
format**, regardless of doctor's outer `--format`. This matters
because `SyncCommand::renderPostCategoryOutput` returns early when
`format === 'json'` (`src/Console/SyncCommand.php:141-145`) — which
means `sync --format=json --prune --prune-orphans` would categorise
and write drift but **skip both prune blocks**. So we must not
propagate `--format=json` to the inner sync.

When doctor's outer format is `text`, inner output streams inline.
When outer is `json`, inner output is captured and discarded via
`Artisan::call($command, $params, $bufferedOutput)`; only the post-fix
report's JSON reaches stdout.

```php
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

if ($this->option('fix') === true) {
    $syncBuffer = $format === 'json' ? new BufferedOutput() : null;
    $leanBuffer = $format === 'json' ? new BufferedOutput() : null;

    $syncExit = Artisan::call('package-boost:sync', [
        '--prune' => true,
        '--prune-orphans' => true,
    ], $syncBuffer);

    $leanExit = Artisan::call('package-boost:lean', [], $leanBuffer);

    // Rebuild report after fixes for final exit + render.
    $report = $this->buildReport($root);
    // Render / encode as usual; exit reflects post-fix hasIssues().
}
```

Why delegation over extracting shared internals:

- `SyncCommand` already enforces every safety guard. Re-extracting
  `SyncWriter::applyPlan` for direct in-process use duplicates wiring
  (frontmatter warnings, prune ordering, MCP categorise).
- `LeanCommand::renderUpdated` is the single non-trivial pure
  transform; calling the command (vs the static) gives consistent
  output components.
- One sync invocation handles drift + orphans + legacy Copilot in a
  single pass — the same order an operator would run today.

Trade-off: in text mode, two output streams stitched together.
Acceptable v1; revisit if operators complain.

---

## 4. JSON Schema

Additive change — bump only if a downstream parser asserts a closed
shape (none known). Default: keep `schema: 1`.

New top-level field, present only when `--fix` ran. Each category
records both the pre-fix attempt and the post-fix outcome so CI can
distinguish "tried and succeeded" from "tried and refused":

```json
{
  "schema": 1,
  "agents": { ... },
  "drift": { ... },
  "frontmatter_issues": [],
  "orphans": [],
  "skill_collisions": [],
  "boost_installed": true,
  "legacy_copilot_instructions": false,
  "gitattributes": { "exists": true, "managed_block_current": true },
  "fix": {
    "skills":        { "attempted": 3, "resolved": 3 },
    "guidelines":    { "attempted": 1, "resolved": 1 },
    "mcp":           { "attempted": "updated", "resolved": "unchanged" },
    "orphans":       { "attempted": 2, "resolved": 2 },
    "legacy_copilot":{ "attempted": true, "resolved": true },
    "gitattributes": { "attempted": true, "resolved": true }
  }
}
```

- `attempted` is captured from the **pre-fix** report: the count or
  status doctor saw before invoking sync/lean.
- `resolved` is derived by comparing pre- vs post-fix state per
  category. Counts: `attempted - post_fix_count`. Booleans:
  pre `true` AND post `false`. MCP: `attempted` = pre-fix status,
  `resolved` = post-fix status (operators read the diff).
- A failure mode like sync refusing to prune the legacy Copilot file
  shows as `{ attempted: true, resolved: false }` — clearly
  distinguishable from a noop (`{ attempted: false, resolved: false }`).

---

## 5. Text Output

Order:

```
Fixing drift…
{sync output}
Fixing .gitattributes…
{lean output}

Doctor (post-fix):
{usual doctor render}
```

Final `hasIssues()` result drives exit. If anything remains, the
re-rendered report tells the operator exactly what (and why it isn't
auto-fixable).

---

## 6. Edge Cases

- **Frontmatter issues do not block sync, only host issues block
  doctor.** `SyncCommand::hasHostFrontmatterIssues` only fails
  `--check` for host `.ai/skills/` content; vendor/shipped malformed
  SKILL.md is warn-only. Doctor's current `DoctorReport::hasIssues()`
  fails on **any** frontmatter issue. This spec aligns doctor with
  sync — see §7. After alignment, vendor/shipped frontmatter
  warnings stay in the report but no longer drive exit `1`, so
  `--fix` cannot be permablocked by upstream skill bugs the operator
  cannot repair.
- **`sync --prune` refusal does not surface via exit code.**
  `SyncCommand::pruneLegacyCopilotInstructions` warns and returns
  silently. `--fix` therefore relies on the rebuilt post-fix report:
  if `legacy_copilot_instructions` is still `true` after delegation,
  exit is `1` and `fix.legacy_copilot.resolved` is `false`. Two
  refusal modes both flow through this path:
  1. User content lives outside the `<package-boost-guidelines>`
     block.
  2. The block has been edited / is out of sync with `.ai/`
     (sync refreshes the host's guideline file but not the legacy
     Copilot file's embedded copy, so a hand-edited block stays
     mismatched after sync).
- **MCP-skip statuses** (`laravel-boost-not-installed`,
  `claude-not-selected`) — already excluded from `hasIssues()`. Sync
  intentionally writes nothing; `--fix` must not flag them either.
- **Idempotency.** Second `--fix` invocation is a noop; all
  underlying ops are already idempotent.
- **Container singleton state.** `SyncCommand::handle()` already
  resets `selectedAgents` and `frontmatterIssues` per invocation
  (`src/Console/SyncCommand.php:104-105`). Calling sync via
  `Artisan::call` from doctor is safe.
- **Tests calling `Artisan::call('package-boost:doctor', ['--fix' => true])`**
  must reset config + filesystem fixtures between runs (existing
  pattern in `tests/DoctorCommandTest.php` / `tests/SyncCommandTest.php`).
- **JSON-format output suppression.** `Artisan::call(..., $buffer)`
  must capture inner-command output when outer `--format=json`,
  otherwise sync's text output corrupts the JSON stream. See §3.

---

## 7. Frontmatter-Block Policy Alignment

`DoctorReport::hasIssues()` currently treats every entry in
`frontmatterIssues` as a hard failure. `SyncCommand::hasHostFrontmatterIssues`
deliberately limits failures to host `.ai/skills/` content. Without
alignment, `doctor --fix` could exit `1` forever because of a
malformed shipped or vendor SKILL.md — a class of issue `--fix`
cannot resolve and the operator cannot patch downstream.

This spec aligns doctor with sync: the rebuilt post-fix report
fails only on **host-owned** frontmatter issues. Vendor / shipped
frontmatter issues stay in the report (so `doctor` and `doctor --fix`
both surface them as warnings) but no longer flip exit code.

Implementation note: refactor `DoctorReport` to either accept a
pre-classified `hostFrontmatterIssues` subset alongside the full
list, or expose a `hostFrontmatterIssues()` filter that mirrors the
sync rule (`str_starts_with($issue['path'], $root . '/.ai/skills/')`).
`hasIssues()` then checks the filtered subset. The full list still
renders in text/JSON output for visibility.

---

## Implementation

### Phase 1: CLI surface (Priority: HIGH)

- [x] Add `--fix` flag to `DoctorCommand::$signature`.
- [x] Branch in `handle()`: when `--fix` set, run delegation block,
  then rebuild report and render as usual.
- [x] Tests — `--fix` alone accepted; `--fix --format=json` accepted;
  `--fix` accepted alongside `--format=text` (the default).

### Phase 2: Delegation to sync + lean (Priority: HIGH)

- [x] ~~Capture pre-fix counters from initial report~~ — deferred to
  Phase 3 with the `fix.{attempted,resolved}` field; Phase 2 ignores
  the pre-fix snapshot.
- [x] Call `package-boost:sync` with `--prune --prune-orphans`. **Do
  not pass `--format`** — sync's json branch returns before the prune
  blocks (see §3).
- [x] Call `package-boost:lean`.
- [x] When outer `--format=json`, route inner output to `NullOutput`
  via the protected `runCommand()` helper. Using
  `Artisan::call(..., $buffer)` instead clobbered the application's
  `lastOutput` pointer, breaking parent-side `Artisan::output()` —
  see Findings.
- [x] Rebuild `DoctorReport` post-fix; render + exit on it.
- [x] Tests — fix-eligible categories per fixture: drift only, orphan
  only, legacy-copilot only (with and without user content),
  gitattributes only, all-clean noop. Plus a `--format=json` test
  that asserts stdout parses as a single JSON document (no leaked
  inner text).

### Phase 3: JSON schema additive `fix` field + frontmatter alignment (Priority: HIGH)

- [x] Refactor `DoctorReport` per §7: added a `hostFrontmatterIssues`
  constructor field; `hasIssues()` checks the host subset only.
  `frontmatterIssues` (full list) still renders in text/JSON output.
- [x] Extend `DoctorReport::toArray()` to include `fix: {...}` only
  when populated. `DoctorCommand::computeFix()` derives the per-category
  `{ attempted, resolved }` payload from a pre/post diff. Pre-fix
  report is built once in `handle()` (passed to `applyFixes`); post-fix
  report is built twice in `applyFixes` — once to compute the diff,
  once to attach the resulting `fix` outcome — so the readonly
  contract stays intact without a `with…` mutator.
- [x] Added phpstan-types `FixOutcome`, `CountFixOutcome`,
  `BoolFixOutcome`, `McpFixOutcome` on `DoctorReport`.
- [x] Tests (in `tests/DoctorCommandTest.php` + new `tests/DoctorReportTest.php`):
  - `--fix --format=json` emits `fix` with correct attempted/resolved
    per category;
  - `--format=json` (no fix) omits the key entirely;
  - `schema` stays `1`;
  - vendor/shipped frontmatter issue alone → `hasIssues()` false
    (unit test on `DoctorReport`);
  - host frontmatter issue alone → `hasIssues()` true (unit test on
    `DoctorReport`);
  - sync prune refusal → `fix.legacy_copilot = { attempted: true,
    resolved: false }`.

### Phase 4: Cross-category integration tests (Priority: MEDIUM)

- [x] Composite fixture with drift + orphan + legacy Copilot + stale
  `.gitattributes`: `--fix` resolves all four in one invocation; exit
  `0`; every `fix.*.resolved` reflects the actual outcome.
- [x] Composite with drift + host frontmatter issue: `--fix` resolves
  drift, frontmatter persists, exit `1`.
- [x] ~~Composite with drift + vendor frontmatter issue~~ — covered
  by the unit test on `DoctorReport::hasIssues()` in
  `tests/DoctorReportTest.php` (planting a vendor SKILL.md would
  pollute the real `vendor/` tree; unit-level coverage is precise
  enough for the host-only rule).
- [x] Legacy Copilot refusal — user content outside the
  `<package-boost-guidelines>` block: `--fix` exit `1`, file
  untouched, `fix.legacy_copilot = { attempted: true, resolved:
  false }` (already added in Phase 3).
- [x] Legacy Copilot refusal — managed block edited / out of sync
  with `.ai/`: `--fix` exit `1`, file untouched, same
  attempted/resolved shape.
- [x] Idempotency: two consecutive `--fix` runs; second is noop,
  exit `0`, every `fix.*.attempted` is zero / `false` /
  `"unchanged"` on the second run.

### Phase 5: Docs (Priority: MEDIUM)

- [x] README — extended the Doctor section under
  `## Usage > One-shot diagnostic` with the `--fix` paragraph,
  per-category resolved-vs-report-only breakdown, and the
  `attempted`/`resolved` JSON contract.
- [x] ~~CHANGELOG entry~~ — deferred to release time per
  `CLAUDE.md` Release Automation: `CHANGELOG.md` is auto-prepended
  from the GitHub release body, drafted in
  `RELEASE_NOTES_vX.Y.Z.md` (gitignored). No manual edit on the
  feature PR.
- [x] ~~README-rendering doc-drift test~~ — no existing pattern in
  this repo; skipped per spec.

---

## Open Questions

1. **JSON schema bump or additive?** Bumping `schema: 1 → 2` is
   safer for closed-shape parsers; additive is cheaper and matches
   "no known consumer" today. Default proposed: additive, keep
   `schema: 1`. Flip if a peer consumer asserts closed shape.

2. **`--fix` log verbosity.** Quiet by default (only the post-fix
   report) or verbose (sync + lean output inline)? Proposed:
   verbose — CI greps anyway and operators benefit from seeing what
   each delegated command did.

3. **Category-subset flag (`--fix=drift,gitattributes`)?** YAGNI for
   v1; revisit if asked. Proposed: defer.

4. **Should `--fix` re-run `doctor` automatically and fail fast if
   the rebuild itself errors?** Edge case (FS race during fix);
   probably overkill. Proposed: do not — let any FS error surface
   from the rebuild path naturally.

5. **Add a `--check` flag on `doctor` for parity with `sync`?**
   Doctor is already read-only by default, so `--check` is
   redundant unless we want a consistent CI vocabulary across
   commands. Proposed: defer; add only if a peer asks.

6. **Patch `SyncCommand` so the JSON branch also runs prune
   blocks?** Would let doctor pass `--format=json` to inner sync
   instead of buffering. Smaller doctor surface; tiny sync
   refactor (plus a JSON-shape change for the prune output).
   Proposed: defer — the buffer approach is local to doctor, no
   sync schema change.

---

## Resolved Questions

1. **Should `--fix` exit code track delegated command exit codes?**
   **Decision:** No — only the rebuilt post-fix `DoctorReport`
   drives exit. **Rationale:** `SyncCommand` warns on prune
   refusal but returns `SUCCESS`; relying on inner exits would
   silently miss partial remediation, especially in JSON mode
   where inner output is suppressed. (Codex review finding.)

2. **Should doctor's `hasIssues()` mirror sync's host-only
   frontmatter rule?** **Decision:** Yes — align doctor with
   sync. **Rationale:** Vendor / shipped malformed SKILL.md
   cannot be fixed by `--fix` or by the operator; treating any
   frontmatter issue as a hard failure makes `--fix`
   permablockable by upstream bugs. The full issue list still
   renders in the report for visibility. (Codex review finding.)

3. **Should the JSON `fix` field record pre-fix intent or
   actual remediation?** **Decision:** Per-category
   `{ attempted, resolved }` pairs. **Rationale:** The
   pre-fix-only shape conflated "attempted" with "succeeded",
   hiding refusals from CI consumers. (Codex review finding.)


## Findings

- **Phase 1.** Added `--fix` flag and stub `applyFixes()` returning
  the pre-fix report unchanged. Two acceptance tests pin the wiring
  (`--fix` alone, `--fix --format=json`). On a clean repo the stub
  is a noop and exits `0`, matching the steady-state behaviour Phase 2
  must preserve.
- **Stale intelephense diagnostics.** Several `P1009 Undefined type`
  warnings surface against `BoostDetector`, `LegacyCopilotInstructions`,
  and `SyncWriter`. The files exist under `src/Console/Internal/` and
  the suite passes. Pre-existing IDE-cache noise; ignored.
- **Phase 2 — `Artisan::call` clobbers `lastOutput`.** Spec §3 originally
  proposed `Artisan::call($command, $params, $buffer)` for inner
  invocations. Implementation revealed that
  `Illuminate\Console\Application::call` reassigns `$this->lastOutput`
  to the supplied buffer on every call. After the inner sync/lean
  calls returned, `Artisan::output()` (used by Pest) read the *inner*
  buffer instead of doctor's outer JSON, so tests saw an empty
  payload. Switched to the protected `runCommand($command, $args,
  $output)` helper from `Illuminate\Console\Command` with
  `Symfony\Component\Console\Output\NullOutput` for JSON mode —
  inner output is discarded without touching `lastOutput`. Spec §3
  prose still accurate (do not propagate `--format=json` to inner
  sync); only the Output class swapped (`BufferedOutput` →
  `NullOutput` via `runCommand`).
- **Phase 3 — `fix` field built outside the readonly value object.**
  `DoctorReport` is `final readonly` and we still target PHP 8.2, so
  no `clone with` semantics are available. Rather than open the class
  up with a mutator, `DoctorCommand::applyFixes` builds the post-fix
  report twice: once to compute the pre/post diff, then once again
  passing the computed `fix` outcome through `buildReport($root, $fix)`.
  Two extra `SyncSources::skills` walks per `--fix` invocation, but
  the pure read paths are cheap and the alternative (mutator on a
  readonly object, or a separate `with`-style factory) leaks
  internals.
- **Phase 3 — host-frontmatter test is a unit test, not integration.**
  Planting a *vendor*-shaped malformed SKILL.md requires writing under
  `vendor/<vendor>/<package>/resources/boost/skills/`, which would
  pollute the repo's real vendor tree. Direct unit tests against
  `DoctorReport` (constructed via a `makeReport()` helper) cover the
  host-only rule precisely without touching the filesystem; the
  existing host-fixture integration test already pins the positive
  case.
- **Final verification — Rector / PHPStan disagreement on a closure
  cast.** `array_filter` with a `static fn (array $issue)` closure
  widens `$issue` to `array<mixed>`, which made
  `NullToStrictStringFuncCallArgRector` insert `(string) $issue['path']`
  before `str_starts_with`. PHPStan then flagged the cast as useless
  (`cast.useless`) because the typed `@param` knows `path` is already
  a string. Replaced the closure with a `foreach` loop in
  `filterHostFrontmatter` so the `@param` shape carries through and
  neither tool has a complaint to make. Both gates now pass on the
  same source.
- **Post-impl evaluate — `legacyCopilotInstructions` flagged
  hand-authored Copilot files.** Doctor used `is_file()` to set the
  flag, but `SyncCommand::pruneLegacyCopilotInstructions` only acts
  on files containing the `<package-boost-guidelines>` tag. Result:
  a hand-authored `.github/copilot-instructions.md` would: (a) make
  doctor exit 1, (b) make `--fix` call sync's prune, (c) sync would
  silently skip (no tag), (d) post-fix flag would still be true →
  permanent exit 1. Switched to
  `LegacyCopilotInstructions::read($root) !== null` to mirror
  sync's gate. Added a regression test for the hand-authored case.
  Pre-existing inconsistency, but `--fix` would have amplified it
  from "annoying warning" to "loop forever".
- **Codex review — shipped-skill ownership.** Codex flagged that
  the host-only relaxation (introduced for downstream CI ergonomics)
  also masked malformed `SKILL.md` under package-boost's own
  `resources/boost/skills/`. In this repo those skills are
  package-maintainer-owned and CI must fail on them. Extended
  `filterHostFrontmatter` to additionally treat any path under the
  package's own `resources/boost/skills/` as blocking — computed as
  `dirname(__DIR__, 2) . '/resources/boost/skills/'` from
  `DoctorCommand`. In a downstream consumer that resolves to
  `vendor/sandermuller/package-boost/resources/boost/skills/`, so
  failures there signal "upgrade your package-boost dependency" —
  also a useful signal. Third-party `vendor/<other>/<other>/...`
  skills stay non-blocking per the original ergonomics goal. Added
  a regression test that plants a malformed skill under shipped and
  asserts exit 1.
- **Codex review — TOCTOU race in `applyFixes`.** Codex flagged
  that `applyFixes` previously called `buildReport($root)` twice —
  once to compute the pre/post diff, once to attach the resulting
  `fix` payload. Any filesystem change between the two reads could
  make the returned report disagree with `fix.*`. Added
  `DoctorReport::withFix(?array): self` (a hand-rolled wither, since
  PHP 8.2 lacks `clone with` for readonly classes). `applyFixes` now
  builds the post-fix report once and uses `withFix` to attach the
  computed payload to the same snapshot. Cuts one `SyncSources`
  walk per `--fix` invocation as a side benefit.
