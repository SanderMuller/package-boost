# Upgrading Skill

## Overview

Ship a vendor-bundled `upgrading` skill alongside `readme` and `release-notes`. It teaches maintainers how to write and maintain `UPGRADING.md` files — the canonical source of truth for migration steps between major versions of a package. Generic guidance lives in SKILL.md; Laravel-specific conventions (version-jump rules, framework-major handling, ecosystem-plugin upgrade patterns) live in `references/laravel-package.md`.

The skill closes a loop opened by the `release-notes` skill: when a release ships breaking changes, the release body's `## Breaking changes` section lists each break as a one-line bullet ending with `See [UPGRADING.md#anchor](UPGRADING.md#anchor) for migration steps.` — operational migration prose stays out of the release body. `upgrading` is the skill that owns *how to write* the UPGRADING.md the bullets link to.

---

## 1. Distribution & Location

Vendor-bundled, same shape as the readme/release-notes skills:

```
resources/boost/skills/upgrading/
├── SKILL.md
└── references/
    └── laravel-package.md
```

Sync transports both files to all 9 agent targets via existing `SyncWriter::linkOrCopy` (`src/Console/SyncWriter.php:14-31`) — no sync code change. Same regression test pattern as the prior spec pins it.

## 2. Skill Frontmatter

```yaml
---
name: upgrading
description: "Helps maintainers write UPGRADING.md / UPGRADE.md files — the canonical source of truth for migration steps between major versions. Teaches per-major sections, before/after code, version-jump rules. Points to `references/laravel-package.md` for ecosystem conventions. Activates when: writing UPGRADING.md, drafting an upgrade guide, planning a major version bump, or user mentions UPGRADING, upgrade guide, migration steps."
---
```

## 3. SKILL.md Structure — `upgrading`

Target ~120 lines. Sections:

- **Purpose** (~10 lines): what this skill does, when to use it, where reference files live, relationship to `release-notes` (release-notes announces breaking change; UPGRADING.md owns the migration steps).
- **Reference pointer** (~5 lines): single advisory sentence with same trigger heuristic as readme/release-notes (laravel/framework OR illuminate/* OR ecosystem deps).
- **When you need an UPGRADING.md** (~10 lines): every breaking release, regardless of package size. For minor/patch with no breaking changes, no update needed. The tiny-package "inline in release body" exception is rejected — it conflicts with the 5d pre-release gate (§6) which requires every `## Breaking changes` bullet to link to a matching UPGRADING.md anchor. UPGRADING.md is cheap (one file, can be 10 lines for a small package); the consistency benefit outweighs the file-creation cost.
- **File structure** (~20 lines): one H1 (`# Upgrade Guide` or `# Upgrading`), reverse-chronological H2 per major transition (`## Upgrading from v4 to v5` first, then `## Upgrading from v3 to v4` below). Within each: estimated upgrade time (optional), prerequisite steps, ordered checklist with before/after code.
- **Per-major section template** (~15 lines): standard sub-headings — "PHP requirements", "Laravel requirements", "Configuration", "Renamed methods/classes", "Removed features", "Behavior changes". Drop sub-headings that don't apply to this transition.
- **Version-jump rules** (~10 lines): if upgrading across multiple majors (e.g. v3 → v5), the user must go through v4 first. State this prominently at the top of the v4→v5 section. Link the v3→v4 section.
- **Before/after code style** (~15 lines): minimal diffs — show only the line that changes, plus 1-2 lines of context. Use `// before` / `// after` comments or labelled fenced blocks. Avoid full-file dumps.
- **Audit pattern** (~15 lines): how to keep UPGRADING.md current — re-run the migration steps against a fresh fixture project on every major release; verify the entry steps still work; cross-link from each release-notes "Breaking" notice to the matching UPGRADING.md anchor.
- **Bookwork to cut** (~10 lines): same discipline as release-notes — cut historical context ("we used to..."), cut rationale paragraphs, cut "Why we made this change" — UPGRADING.md is operational, not narrative.
- **Cross-refs** (~10 lines): pointers to shipped sibling skills — `release-notes` (which defers here on breaking changes), `readme` (which links to UPGRADING.md), `lean-dist`, `package-development`. Do NOT cross-ref `pre-release` (host-internal).

Hard cap: 140 lines.

## 4. Reference Files

Same pattern as readme/release-notes — plain markdown, no frontmatter, no schema. v1 ships one reference per skill:

```
resources/boost/skills/upgrading/references/laravel-package.md
```

Target 80–120 lines. Cover Laravel-package-specific conventions:

- **Spatie pattern** — observed structure across spatie/laravel-medialibrary, spatie/laravel-permission, spatie/laravel-data UPGRADING.md files.
- **Filament major upgrade pattern** — comprehensive multi-section guides; reference Filament's own upgrade guide as a model.
- **Laravel-itself UPGRADE pattern** — `laravel/framework`'s `UPGRADE.md` as a canonical example: per-version major H2, "Likelihood Of Impact: High/Medium/Low" sub-headings, per-area H3.
- **Composer constraint advice** — when a package drops a Laravel major (e.g. drops Laravel 10), the UPGRADING.md needs a "Composer constraint update" section showing the new `composer.json` line.
- **Service provider auto-discovery** — note that auto-discovery covers the SP; users don't need to re-register manually after upgrade unless the package opts out.
- **Testbench version pinning** — when a package's tests pin a Testbench version, downstream consumer test suites may need a matching bump.
- **Anti-patterns specific to Laravel** — e.g. instructing users to "run `php artisan vendor:publish --tag=… --force`" without warning that this overwrites local edits.

## 5. Reference Loading: Worked Example

Same advisory pattern as readme/release-notes. Agent reads SKILL.md, sees the pointer, checks `composer.json`, loads the reference if any `illuminate/*` / `laravel/framework` / ecosystem dep is present. Best-effort, not contractual. Same repo verification surface (file transport via sync, content review at PR time).

## 6. Consumer Wiring

Five edits across four skill files. Beyond these, no other skill files are touched.

- **`release-notes` skill** (`resources/boost/skills/release-notes/SKILL.md`): in the "Override structure" section, **keep the existing `## Breaking changes` bullet format** but require each bullet to end with a link to the matching UPGRADING.md anchor. Update the example block from `<what breaks, with before/after code snippet if non-trivial>` / `<upgrade path or link to UPGRADING.md>` to a single contract — every bullet shaped as `<what breaks>. See [UPGRADING.md#anchor](UPGRADING.md#anchor) for migration steps.` Operational steps (before/after code, multi-line migration) move out of the release body entirely; UPGRADING.md owns them.
- **`release-notes/references/laravel-package.md`**: cross-link the `upgrading` skill in the existing "Long upgrade prose embedded in the release body" anti-pattern row.
- **`readme` skill** (`resources/boost/skills/readme/SKILL.md`): the **Recommended sections** list already includes "Upgrading — link to UPGRADING.md or inline notes for major bumps". Tighten to: "Upgrading — link to UPGRADING.md (use the `upgrading` skill to write it)."
- **`pre-release` skill** (host-internal, `.ai/skills/pre-release/SKILL.md`), **three edits**:
  - `5a. README` block: add a one-line pointer that breaking releases should also produce/update an UPGRADING.md via the `upgrading` skill.
  - `5c. References` matrix row: extend the glob to include `upgrading` (`resources/boost/skills/{readme,release-notes,upgrading}/references/*.md`).
  - **New `5d. UPGRADING.md` matrix row** (conditional gate, fires only when this release has breaking changes): pass criteria — "UPGRADING.md updated for this breaking release **and** every bullet under the release body's `## Breaking changes` section ends with a link to a matching UPGRADING.md anchor (per the release-notes Override structure contract)". Without this gate, breaking releases can pass the matrix while shipping a stale guide that release-notes now intentionally defers to.

## 7. Maintenance Cadence

UPGRADING.md content stabilizes per-major and rarely changes after a major release ships. Reference-file drift is the bigger concern. Trigger review:

- Major Laravel release (annual) — version-constraint examples, Testbench pinning conventions.
- Major Filament release — ecosystem-plugin upgrade-guide patterns.
- Major Spatie laravel-package-tools change (rare) — affects boilerplate examples.

Add a one-line checkbox to the `pre-release` skill's references-review row prompting confirmation that the upgrading reference has been reviewed since the last ecosystem major.

## 8. Tests

Pure guidance skill, no PHP logic to test. Sync coverage only:

- Update `tests/SyncCommandTest.php` `SHIPPED_SKILLS` constant to include `upgrading`.
- Update `SHIPPED_SKILLS_WITH_REFERENCES` to add `['upgrading', 'laravel-package.md']`. Existing dataset tests (symlink-path + copyDirectory-fallback) cover the new skill automatically.

## 9. Documentation

- Add one bullet to repo `README.md` shipped-skills list.
- **Do NOT edit `CHANGELOG.md`** — auto-updated on release per `CLAUDE.md:116`. When this work ships in a release, write the release body using the `release-notes` skill.

---

## Implementation

### Phase 1: Reference content research (Priority: HIGH)

- [x] Survey 4–6 high-quality UPGRADING.md files: Spatie laravel-medialibrary, Spatie laravel-permission, Spatie laravel-data, Filament's main upgrade guide, Laravel's own `UPGRADE.md` in `laravel/framework`. Note structure, sub-heading conventions, before/after style, version-jump handling, file-naming convention (UPGRADING.md vs UPGRADE.md).
- [x] Distill findings into draft outline for `references/laravel-package.md`. Commit outline to `## Findings` section of this spec — that's the Phase 1 artifact.
- [x] Tests — none (research phase). Findings entry exists before Phase 2 starts.

**Out of scope for Phase 1:** patch/minor-release upgrade-guide patterns and deprecation-foreshadowing conventions. The skill's stated boundary (§3 "When you need an UPGRADING.md") is major-bumps-only — surveying patch patterns would pull deprecation guidance back into the skill and weaken the boundary with `release-notes`. Deprecations stay in release-notes / CHANGELOG until the breaking release lands.

### Phase 2: Author skill + reference (Priority: HIGH)

- [x] Create `resources/boost/skills/upgrading/SKILL.md` per §3 structure. Hard cap 140 lines. **(106 lines.)**
- [x] Create `resources/boost/skills/upgrading/references/laravel-package.md` — plain markdown, no frontmatter, 80–120 lines, content per §4. **(92 lines.)**
- [x] Update `tests/SyncCommandTest.php` — add `upgrading` to `SHIPPED_SKILLS`, add `['upgrading', 'laravel-package.md']` to `SHIPPED_SKILLS_WITH_REFERENCES`. **(Done — both constants extended.)**
- [x] Run `vendor/bin/testbench package-boost:sync` locally; verify both files land in `.claude/skills/`, `.cursor/skills/`, and the other 4 unique agent target dirs. **(Sync wrote 6 new skill dirs across all 6 unique agent targets; spot-checked `references/laravel-package.md` resolves through the symlink.)**
- [x] Tests — extended dataset tests cover the new skill automatically. Run `vendor/bin/pest tests/SyncCommandTest.php --no-coverage` and confirm green. **(70 pass, 287 assertions; was 67 / 275 — gained the 3 new dataset entries × 1 assertion each in the symlink test + 6 new dataset entries × 2 assertions in the copyDirectory-fallback test.)**

### Phase 3: Cross-reference existing skills (Priority: MEDIUM)

- [x] Update `resources/boost/skills/release-notes/SKILL.md` "Override structure" section per §6 contract — kept `## Breaking changes` bullet format, every bullet now ends with `See [UPGRADING.md#anchor](UPGRADING.md#anchor) for migration steps`. Added explicit "bullet contract" paragraph naming the `5d` gate as enforcer.
- [x] Update `resources/boost/skills/release-notes/references/laravel-package.md` — extended the existing "Long upgrade prose" anti-pattern row to mention the `upgrading` skill and the bullet-link contract.
- [x] Update `resources/boost/skills/readme/SKILL.md` Recommended sections — Upgrading bullet now reads "link to UPGRADING.md (use the `upgrading` skill to write it). The release-notes contract requires this link to resolve."
- [x] Update `.ai/skills/pre-release/SKILL.md` `5a. README` block — added pointer that breaking releases must produce/update UPGRADING.md and that the `5d` gate enforces every breaking bullet links to an anchor.
- [x] Update `.ai/skills/pre-release/SKILL.md` `5c. References` matrix row — glob extended to `{readme,release-notes,upgrading}`.
- [x] Add new `5d. UPGRADING.md` row to the Quick Reference matrix — conditional gate firing only on breaking releases, action and pass criteria per spec.
- [x] Re-sync, confirm targets updated. **(Sync clean; drift-check clean.)**
- [x] Tests — sync drift check passes. **(70 / 287, all green.)**

### Phase 4: Repo docs (Priority: MEDIUM)

- [x] Add `upgrading` bullet to `README.md` shipped-skills list, matching the "Ships a X skill that …" pattern of the readme + release-notes bullets. **(Done.)**
- [x] Do NOT touch `CHANGELOG.md` (auto-updated on release per `CLAUDE.md:116`). **(Confirmed untouched.)**
- [x] Tests — none.

### Phase 5: Lint & release prep (Priority: LOW)

- [x] Run the `pre-release` skill end-to-end (`.ai/skills/pre-release/SKILL.md`). It is the **only** canonical verification source for this phase. Do NOT summarize, subset, or paraphrase its checklist here; follow it as written at the time of completion. **(Done — see Phase 5 Findings entry below.)**
- [x] Tests — `pre-release` skill's matrix table all-green per its own pass criteria. **(All 8 rows green incl. the new 5d row N/A.)**

---

## Open Questions

1. **Single combined "documentation" skill instead of three separate (readme, release-notes, upgrading)?** They share trigger shape, reference layout, audience, sync mechanics. Combining as `docs` reduces maintenance overhead. Splitting keeps trigger surfaces sharp. Lean: stay split — each activates from a different verb (write README, draft release notes, write upgrade guide) and produces a different artifact. The combined-skill door stays open if maintenance burden grows.

None.

---

## Resolved Questions

1. **What's the boundary between `release-notes` and `upgrading`?** **Decision:** `release-notes` announces *what* changed (terse breaking notice + UPGRADING.md link); `upgrading` owns *how* to migrate (step-by-step, before/after code, version-jump rules). When breaking changes exist, release-notes defers operational steps to UPGRADING.md. **Rationale:** Avoids drift between two locations documenting the same migration. Matches the established pattern in surveyed packages (Spatie, Filament, Laravel itself).

2. **UPGRADING.md vs UPGRADE.md vs UPGRADE_GUIDE.md filename convention?** **Decision:** Recommend `UPGRADING.md` at package root as the simplest default, but explicitly accept four observed variants: root `UPGRADING.md` (Spatie laravel-medialibrary, laravel-data), `docs/upgrading.md` (Spatie laravel-permission), `upgrade.md` in a separate docs repo per branch (laravel/framework), `docs/<n>-upgrade-guide.md` per major branch (Filament). README must link to whichever location the package uses. **Rationale:** Phase 1 finding — no single dominant convention exists; Spatie itself ships two locations. Recommending a default avoids analysis paralysis; accepting variants matches reality. The release-notes `## Breaking changes` bullet contract requires a stable link target, not a specific filename.

## Findings

### Phase 1 (2026-05-05)

Surveyed 5 upgrade guides: spatie/laravel-medialibrary, spatie/laravel-permission, spatie/laravel-data, laravel/framework, filamentphp/filament. **Several pre-survey assumptions were wrong** — flagged below.

#### A. Filename convention is much messier than OQ#2's lean assumed

Five surveyed packages, four distinct filename conventions:

| Package | Path | Note |
|---|---|---|
| spatie/laravel-medialibrary | `UPGRADING.md` (root) | Capital, package root |
| spatie/laravel-data | `UPGRADING.md` (root) | Same |
| spatie/laravel-permission | `docs/upgrading.md` | **Lowercase, in docs/** — Spatie inconsistency |
| laravel/framework | `upgrade.md` in **laravel/docs:<branch>** | Lowercase, separate repo, per-major branch |
| filamentphp/filament | `packages/.../docs/14-upgrade-guide.md` | Per-major branch, rendered to filamentphp.com |

No single dominant pattern. The "Spatie uses UPGRADING.md" claim from OQ#2 is wrong — Spatie itself ships two locations. **Implication for the reference:** recommend `UPGRADING.md` at package root as the simplest default (works for 2/5 surveyed; matches general expectation), but explicitly acknowledge the four real variants and tell maintainers their file location should be linkable from the README.

OQ#2 should resolve to: recommend `UPGRADING.md` at root, accept the variants, ensure README links to whichever location the package uses.

#### B. Before/after style: prose-dominant, NOT labelled-pair as I assumed

My pre-survey draft assumed Spatie uses "labelled fenced blocks Before/After". Reality:

- **Spatie laravel-medialibrary, laravel-permission**: prose-dominant ("rename X to Y") with full PHP/migration code blocks; **no labelled before/after pairs, no diff syntax**.
- **Spatie laravel-data**: adjacent code blocks with `// v3` / `// v4` comment labels — closer to my pre-survey assumption.
- **Laravel framework**: adjacent code blocks with `// Laravel <= 12.x` / `// Laravel >= 13.x` comments.
- **Filament**: adjacent fenced blocks in prose ("In v4, the following code is required instead:"); no two-column layout.

**No surveyed package uses git-diff syntax** for migration code. The reference should describe the version-comment-labelled adjacent-blocks pattern as the cleanest option (Laravel framework + Spatie laravel-data evidence) without prescribing it as the only way.

#### C. Impact-tagging is more widespread than expected (3 of 5)

- **Laravel framework**: `Likelihood Of Impact: High|Medium|Low|Very Low` callout per H3 change, plus top-of-file index sections grouping changes by impact level.
- **spatie/laravel-data**: `High impact changes` / `Low impact changes` H3 buckets within v1→v2 and v2→v3 sections; per-change H/M/L tags.
- **Filament**: breaking changes bucketed High-impact (6) / Medium-impact (5) / Low-impact (9).

Strong, repeatable pattern — **promote in the reference as the recommended way to triage long upgrade guides**. Especially valuable for guides over ~200 lines where readers need to skip to what affects them.

#### D. Version-jump rules: NOT observed in any surveyed package

My pre-survey draft prescribed "encode transitive version-jump rules (v3 → v5 means do v3→v4 first then v4→v5)". **No surveyed package ships explicit jump-rule guidance.** They imply sequential walk-through via reverse-chronological H2 ordering, but never state it. spatie/laravel-permission funnels everyone through a shared "Upgrade Essentials" preamble checklist, which is the closest thing to a cross-version rule.

**Implication:** the spec's §3 "Version-jump rules" subsection is aspirational, not observed. Two paths:
- (a) Keep the prescription — frame as a quality improvement readers will appreciate even though existing packages don't ship it.
- (b) Drop it — match observed practice; note in the reference that jump-rule guidance is "rare but recommended" and let maintainers add it if they want.

Lean: (a). Concrete prescriptions on a frequent failure mode (users skipping a major and breaking) beat matching the lazy default. The reference frames it as "go beyond the surveyed baseline."

#### E. Filename / structural patterns to teach

- **Reverse-chronological H2 per major transition.** Universal in the survey (5/5). Newest at top so existing users find their starting point first.
- **"Upgrade Essentials" / pre-flight checklist preamble.** Spatie permission is the lone surveyed example — list of universal steps (back up DB, check requirements, etc.) applied across all transitions. Smart pattern; promote in reference as a recommended addition.
- **Per-major sub-heading style varies.** No dominant pattern. Spatie permission v6→v7 uses topical H3s (Requirements, Service Provider, Event Class Renames, Command Class Renames, Removed Deprecated Methods, Type Hints). Laravel framework uses per-component H3s (Cache, Container, Database, etc.). Reference should suggest topical H3s as a default but accept per-component for framework-style packages.
- **Estimated upgrade time callout.** Laravel framework only ("Estimated Upgrade Time: ~10 Minutes"). Optional but useful for major transitions.
- **Automated upgrade tooling references.** Laravel framework points at Laravel Shift and Laravel Boost (`/upgrade-laravel-v13` prompt). Filament ships an automated upgrade script with cross-platform caveats. Worth a reference section: "Mention any automation that exists; don't expect users to know about it."

#### F. Length reality

Surveyed file lengths: 420–850 lines. Long. The reference (target 80–120 lines) doesn't replicate these — it teaches how to *write* one. The line-count gap is intentional.

#### G. Recommended outline for `upgrading/references/laravel-package.md` (~100 lines)

1. **Filename + location** — recommend `UPGRADING.md` at root; accept the four variants (with examples); README must link to it.
2. **Reverse-chronological H2 sections** — "From v4 to v5" first, "From v3 to v4" below it. Universal pattern.
3. **Optional pre-flight "Upgrade Essentials" preamble** — Spatie permission's pattern. Universal steps applied across all transitions.
4. **Sub-heading style: topical H3s** — Requirements, Configuration, Renamed methods/classes, Removed features, Behavior changes. Drop sub-headings that don't apply to the transition.
5. **Impact tagging** — adopt the Laravel-framework `Likelihood Of Impact: High|Medium|Low` pattern for guides over ~200 lines. Most-impactful changes first within each major.
6. **Before/after code style** — adjacent fenced blocks with version-comment labels (`// Laravel <= 12.x` / `// Laravel >= 13.x` or `// v3` / `// v4`). Show only the changed lines plus 1–2 of context. No git-diff syntax.
7. **Composer constraint update** — every major-transition section should show the `composer.json` constraint change (`"vendor/package": "^5.0"`).
8. **Version-jump rule** — go beyond observed practice and state explicitly: "Upgrading from v3 to v5? Complete v3→v4 first, then v4→v5." Place at the top of every transition section that has a predecessor.
9. **Automated upgrade tooling** — if it exists (Laravel Shift, Boost upgrade prompt, custom script), call it out near the top of the section.
10. **Auto-discovery + `vendor:publish --force`** — note the auto-discovery doesn't need re-registration; warn that `--force` overwrites local edits to published config.
11. **Testbench version pinning** — note when the package's Testbench requirement bumps, downstream test suites may need a matching change.
12. **Anti-patterns** (table) — rationale prose ("we changed X because Y"), full-file dumps, missing version-jump warning, `--force` without warning, anchor names that don't match release-notes' `## Breaking changes` link targets.
13. **Cross-refs** — `release-notes` (defers here), `readme` (links here), `lean-dist`, `package-development`.

#### H. Phase 2 implications

- The breaking-change link contract from the prior spec's release-notes work (`See [UPGRADING.md#anchor]`) requires UPGRADING.md to ship stable anchor IDs. H2 headings auto-generate kebab-case anchors in GitHub-rendered markdown — `## Upgrading from v4 to v5` → `#upgrading-from-v4-to-v5`. Reference should warn against renaming H2s after release, since release-notes links would break.
- Resolve OQ#2 in Phase 2a: recommend `UPGRADING.md` at root, accept the four observed variants.
- §3 "Version-jump rules" subsection stays but reference notes it's a quality improvement beyond observed baseline.

### Phase 5 (2026-05-05)

`pre-release` skill matrix run end-to-end after the Phase 3 wiring landed:

| Step           | Outcome                                                              |
|----------------|----------------------------------------------------------------------|
| 1. Rector      | OK, 0 files changed                                                  |
| 2. Pint        | passed                                                               |
| 3. PHPStan     | 0 errors                                                             |
| 4. Pest (full) | 124 pass / 411 assertions / 0 failures (was 121/399 pre-Phase 2)     |
| 5a. README     | audit pattern applied — new bullet accurate, no stale claims         |
| 5b. Boost docs | sync drift-check clean                                               |
| 5c. References | all 3 references current vs ecosystem (readme, release-notes, upgrading) |
| 5d. UPGRADING.md | N/A — this work is a feature add, no breaking changes              |

Notable: 5d row is the gate this spec introduced; first run exercised the "skip when no breaking changes" branch correctly. The breaking-changes branch will be exercised the first time a real breaking release lands.

`composer update --prefer-lowest|--prefer-stable` matrix runs skipped per pre-release skill's gate — changes are markdown + Pest dataset constant additions, not version-sensitive. CI matrix exercises all cells regardless.

<!-- Notes added during implementation. Do not remove this section. -->
