# README + Release Notes Skills

## Overview

Ship two vendor-bundled skills — `readme` and `release-notes` — that help maintainers write good README files and release notes. Both skills are generic by default and consult ecosystem-specific reference files (e.g., `references/laravel-package.md`) when the project context matches. No classification engine, no detection algorithm — the skill body teaches structure, voice, and audit patterns; the reference files carry ecosystem-specific section orderings and idioms.

These two skills replace the abandoned `package-types` taxonomy spec. Classification of package shapes belongs in a project-skeleton tool (e.g., spatie/package-skeleton), not here. Reference-file harvest from the abandoned work is captured under §11.

---

## 1. Distribution & Location

Two new vendor-bundled skills alongside `package-development`, `lean-dist`, etc.:

```
resources/boost/skills/readme/
├── SKILL.md
└── references/
    └── laravel-package.md      # initial reference; more added later

resources/boost/skills/release-notes/
├── SKILL.md
└── references/
    └── laravel-package.md
```

Sync transports both SKILL.md and the `references/` subtree to all 9 agent targets (`.claude/skills/`, `.cursor/skills/`, etc.). Existing `SyncWriter::linkOrCopy` at `src/Console/SyncWriter.php:14-31` already copies entire skill directories — no sync code change needed. A regression test in §9 pins this behavior.

## 2. Skill Frontmatter

### `readme` skill

```yaml
---
name: readme
description: "Helps maintainers write or improve README files. Teaches required sections, structure, voice, and audit patterns. Loads ecosystem-specific reference (e.g., references/laravel-package.md) when context matches. Activates when: writing a README, improving an existing README, auditing a README for staleness, or user mentions readme, README.md, project documentation, package docs."
---
```

### `release-notes` skill

```yaml
---
name: release-notes
description: "Helps maintainers draft GitHub release bodies / release notes from a tag range. Covers highlights, breaking changes, upgrade path. Loads ecosystem references when context matches. Activates when: writing release notes, drafting a release announcement, summarizing a tag range, or user mentions release notes, GitHub release, RELEASE_NOTES."
---
```

Both descriptions stay under ~400 chars to avoid platform truncation.

## 3. SKILL.md Structure — `readme`

Target ~140 lines. Sections:

- **Purpose** (~10 lines): what this skill does, when to use it, where reference files live.
- **Reference pointer** (~5 lines): single advisory sentence — "Working on a Laravel package (`composer.json`'s `require` lists any `illuminate/*`)? See `references/laravel-package.md` for ecosystem-specific guidance." Advisory, not contractual (see §5).
- **Required sections** (~15 lines): generic list every README needs — title + one-line tagline, install, basic usage, configuration (if any), testing, license. Cross-cutting — apply regardless of project type.
- **Recommended sections** (~10 lines): badges, version matrix (when relevant), upgrading, contributing, security policy link, changelog link, sponsors/credits.
- **Voice & structure** (~20 lines): active voice, code-first (show before tell), present tense, one H1 only, scannable bullet/table density, link to deeper docs rather than inline novella.
- **Audit pattern** (~20 lines): canonical README-staleness audit — scan against recent commits (`git log <last-tag>..HEAD`), check for stale install instructions, dead config keys, removed features still mentioned, broken example code. This is the **single source of truth** (per Resolved Question 4); host-internal `pre-release` skill cross-references this section.
- **Common mistakes** (~15 lines): table of don'ts (no "TODO" sections shipped, no broken example code, no duplicate badges, no out-of-date version table).
- **Cross-refs** (~10 lines): pointers to shipped sibling skills only — `release-notes`, `lean-dist`, `package-development`. Do NOT cross-ref `pre-release` (host-internal at `.ai/skills/`, not shipped via `resources/boost/skills/` — consumers won't have it).

(No maintenance-cadence subsection inside SKILL.md — that's spec-author concern, lives in §8, not surfaced to the agent reader.)

Hard cap: 160 lines. If exceeded, split common mistakes into a reference.

## 4. SKILL.md Structure — `release-notes`

Target ~120 lines. Sections:

- **Purpose** (~10 lines): what this skill does, inputs (tag range or CHANGELOG entry), outputs (markdown body for GitHub release / `RELEASE_NOTES_<version>.md`).
- **Reference pointer** (~5 lines): single advisory sentence, same pattern as `readme` — "Working on a Laravel package? See `references/laravel-package.md` for version-matrix shifts, breaking-change conventions, and CHANGELOG format notes."
- **Default: GitHub auto-generator** (~15 lines): for patch and most minor releases, GitHub's "Generate release notes" output is sufficient — single `## What's Changed` H2 with PR-linked bullets plus `**Full Changelog**:` footer. Survey of 5 well-maintained Laravel packages (see Phase 1 Findings) confirmed this is the dominant 2026 pattern. Reference includes a worked example.
- **When to override** (~10 lines): only for major version bumps, breaking changes, multi-PR features, or ecosystem dep changes. Don't override for routine work.
- **Override structure** (~15 lines): layered on top of the auto-bullets, conditional sections — one-line summary, optional **Highlights** (≤ 5 items), optional **Breaking changes** (with upgrade-path code snippets, only when real). Drop sections that don't apply rather than ship empty.
- **Tag-range workflow** (~20 lines): how to derive notes from `git log <last-tag>..HEAD`, the `git describe --tags --abbrev=0` recipe, breaking-change footer scan, GitHub's "Generate release notes" button.
- **CHANGELOG workflow** (~10 lines): how to handle hand-written CHANGELOG entries (mirror, don't re-derive) and CHANGELOG-automation workflows (release body becomes CHANGELOG entry verbatim).
- **Voice** (~10 lines): user-facing language (talk about behavior, not internal refactors), link PRs/issues for technical readers, lead with impact.
- **Cross-refs** (~10 lines): pointers to shipped sibling skills only — `readme` (when a release ships sections that need README updates), `lean-dist`, `package-development`. Do NOT cross-ref `pre-release` (host-internal, not shipped to consumers).

Hard cap: 140 lines.

## 5. Reference Files

Reference files are plain markdown — **no frontmatter, no schema, no matcher algorithm, no auto-load contract**. v1 ships one reference per skill (`references/laravel-package.md`).

### How references reach the agent

SKILL.md mentions the reference and when it's relevant, in prose. Example:

> Working on a Laravel package (`composer.json`'s `require` lists any `illuminate/*`)? See `references/laravel-package.md` in this skill directory for ecosystem-specific section ordering, version-matrix conventions, and Testbench testing snippets.

This is advisory, not contractual. We make no testable claim that any specific agent will read the reference; we only commit to (a) shipping SKILL.md with the pointer and (b) shipping the reference file alongside via sync. What the agent does with the pointer is the same kind of best-effort behavior every other shipped skill in this repo relies on (e.g., `package-development` says "use `vendor/bin/testbench` not `php artisan`" — also unverifiable, also load-bearing).

The repo's verification surface is therefore: sync transports the files (tested in §9), SKILL.md content matches §3/§4 (review at PR time), and the reference content matches §11 harvest (review at PR time). Beyond that, agent behavior is out of scope for repo tests.

### Scaling

If/when more references exist (≥3) or pointers become genuinely ambiguous in prose, **then** introduce a structured matcher with a tested loader. v1 deliberately has no such structure.

### Initial references (Phase 2)

- `resources/boost/skills/readme/references/laravel-package.md`
- `resources/boost/skills/release-notes/references/laravel-package.md`

Both ~80–120 lines, pure markdown. Cover Laravel-package-specific concerns: PHP/Laravel version-matrix table, `composer require` install, service provider auto-discovery note, Testbench testing snippet, `lean-dist` cross-reference for `.gitattributes` discipline, and section ordering as observed in well-maintained Spatie/BeyondCode/Laravel-core packages.

### Later references (out of scope for v1)

Listed so the file-layout direction is clear — none ship in v1:

- `laravel-application.md`, `generic-php-library.md`, `cli-tool.md`.

### Initial references (Phase 2)

- `resources/boost/skills/readme/references/laravel-package.md`
- `resources/boost/skills/release-notes/references/laravel-package.md`

Both ~80–120 lines. Cover Laravel-package-specific concerns: PHP/Laravel version-matrix table, `composer require` install, service provider auto-discovery note, Testbench testing snippet, `lean-dist` cross-reference for `.gitattributes` discipline, and section ordering as observed in well-maintained Spatie/BeyondCode/Laravel-core packages.

### Later references (out of scope for v1, listed for direction)

- `laravel-application.md` — full Laravel apps differ from packages (no version matrix, deployment section, env vars).
- `generic-php-library.md` — non-Laravel PHP packages.
- `cli-tool.md` — Symfony Console / Laravel Zero binaries.

Skip these for v1; the load-condition pattern keeps the door open.

## 6. Worked Example

Package's `composer.json` has `require: { "php": "^8.2", "illuminate/contracts": "^11" }`. Agent invoked for a README task reads SKILL.md, sees the advisory pointer to `references/laravel-package.md`, recognizes the `illuminate/*` cue, and consults the reference. Generic guidance still applies; the reference adds the Laravel-specific layer (Spatie-style section ordering, version matrix table, Testbench testing snippet).

A non-Laravel package shows no `illuminate/*` dep. Agent reads the same pointer, sees it doesn't apply, ignores the reference. Skill emits generic guidance only.

This is best-effort behavior, same as every other shipped skill. Failure mode is identical to the no-reference baseline. No verification is in scope for this repo.

## 7. Consumer Wiring

Three changes only — beyond these, no other skill files are touched in this spec's scope:

- **`pre-release` skill, README-staleness section** (`.ai/skills/pre-release/SKILL.md:72`): add one line — "Use `readme` skill to fix gaps; use `release-notes` skill to draft notes."
- **`pre-release` skill, matrix table** (`.ai/skills/pre-release/SKILL.md:110`): add a maintenance-cadence checkbox per §8 — "Reference files reviewed since last Laravel/Filament/Livewire major release."
- **`package-development` skill** (`resources/boost/skills/package-development/SKILL.md:78`, end of **Authoring guidelines** section near line 91): append "See also: `readme` and `release-notes` skills for documentation generation."

## 8. Maintenance Cadence

Reference files drift slower than detection signals would have. Trigger review:

- Major Laravel release (annual) — version matrix examples, Testbench/Pest version notes.
- Significant CHANGELOG.md format shifts in the Laravel ecosystem (e.g., Keep-a-Changelog vs free-form).
- Conventional-commits adoption shifts (affects release-notes derivation).

Add a one-line checkbox to `pre-release` skill prompting confirmation that the references have been reviewed since the last ecosystem major.

## 9. Tests

No PHP logic to test — these are guidance skills. Sync coverage only.

Status of existing test `tests/SyncCommandTest.php` (verified in this spec, not at implementation time):
- Maintains a hardcoded shipped-skills list at line 8 — adding `readme` and `release-notes` requires editing that list.
- Asserts `is_link($targetDir)` and `File::exists($targetDir/SKILL.md)` for each shipped skill.
- Does NOT currently assert nested files inside the symlinked dir. Sync transports them transparently because `SyncWriter::linkOrCopy` (`src/Console/SyncWriter.php:14-31`) symlinks the whole source dir, but no test pins the behavior.

Phase 2 test additions:
- Update the hardcoded shipped-skills list at `tests/SyncCommandTest.php:8` to include `readme` and `release-notes`.
- Add one assertion per new skill: `File::exists($targetDir/references/laravel-package.md)` after sync. This is the regression guard against a future `linkOrCopy` refactor narrowing to single-file copies.
- Drift check: re-run sync, confirm zero diff (covered by existing idempotency tests; verify `readme` / `release-notes` participate).

## 10. Documentation

- Add two bullets to repo `README.md` under the shipped skills list (one per new skill).
- **Do NOT edit `CHANGELOG.md` manually.** Per `CLAUDE.md:116`, CHANGELOG is auto-updated on release by `.github/workflows/update-changelog.yml` (`stefanzweifel/changelog-updater-action`), which prepends the GitHub release body. The release body for the version that ships these skills should describe them — write that body using the new `release-notes` skill (meta-recursive but correct).

## 11. Harvest from Abandoned `package-types` Spec

The deleted `package-types` spec produced research worth carrying forward into the reference files (do not re-vendor the corpus YAML — extract the conventions directly into prose):

- **Section ordering observed in surveyed Spatie packages** (laravel-permission, laravel-medialibrary, laravel-backup, laravel-data, laravel-csp): one-line tagline, badges row, sponsorship/Spatie pitch (skip for non-Spatie), install via `composer require`, optional config publish, basic usage, advanced/named-feature sections, testing, changelog link, contributing, security policy, credits, license. This becomes the spine of `references/laravel-package.md` for `readme`.
- **Version matrix expectations**: matrix table with PHP version × Laravel version × package version; readers expect this in any package supporting more than one Laravel major.
- **Service provider auto-discovery**: standard since Laravel 5.5; reference notes that manual registration instructions are stale unless the package opts out of discovery.
- **Testbench testing snippet**: package READMEs increasingly show `vendor/bin/testbench package:test` or `vendor/bin/pest`; reference encodes the canonical snippet.
- **Filament v5 split between `filament/filament` and `filament/support`**: relevant for Filament-plugin package READMEs since install instructions differ. Note in reference (a brief sub-section), don't algorithm-detect.
- **`.gitattributes` export-ignore for `.ai/`, `.claude/`, etc.**: cross-reference to `lean-dist` skill.
- **Rare clean migration-only packages**: not a section in the reference; just informs that "schema-only" packages should still document model usage downstream consumers expect.

These bullets become drafted prose during Phase 2, not a verbatim copy.

---

## Implementation

### Phase 1: Reference content research (Priority: HIGH)

- [x] Survey README files of 8–10 high-quality Laravel packages (Spatie permission/medialibrary/backup/data/csp, barryvdh/laravel-debugbar, beyondcode/laravel-dump-server, Laravel Socialite/Horizon, awcodes/filament-curator, wire-elements/spotlight). Note section ordering, headings, common idioms.
- [x] Survey 4–6 release notes (GitHub Releases pages) — surveyed 5: spatie/laravel-permission, spatie/laravel-data, laravel/horizon, awcodes/filament-curator, barryvdh/laravel-debugbar. Note structure, voice, severity-driven format.
- [x] Distill findings into draft outlines for `references/laravel-package.md` (one per skill). Committed to `## Findings` below.
- [x] Tests — none (research phase). Findings entry exists before Phase 2 starts.

### Phase 2: Author both skills + initial references (Priority: HIGH)

- [x] Create `resources/boost/skills/readme/SKILL.md` per §3 structure. Hard cap 160 lines. **(87 lines.)**
- [x] Create `resources/boost/skills/readme/references/laravel-package.md` — **plain markdown, no frontmatter, no schema**. Body 80–120 lines, content per §11 harvest. **(98 lines.)**
- [x] Create `resources/boost/skills/release-notes/SKILL.md` per §4 structure. Hard cap 140 lines. **(92 lines.)**
- [x] Create `resources/boost/skills/release-notes/references/laravel-package.md` — **plain markdown, no frontmatter, no schema**. Body 80–120 lines. **(67 lines — under target; content felt complete without padding.)**
- [x] Run `vendor/bin/testbench package-boost:sync` locally; verify both skill dirs (SKILL.md + references/) land in `.claude/skills/`, `.cursor/skills/`, and the other 7 agent targets. **(Verified — sync output reported 12 new skill dirs across the 6 unique agent target dirs; spot-checked symlinks resolve and references/laravel-package.md is reachable in .claude/, .cursor/, .kiro/.)**
- [x] Tests — extend `tests/SyncCommandTest.php` with one assertion per skill verifying that `references/laravel-package.md` propagates. **(Done: added `SHIPPED_SKILLS_WITH_REFERENCES` constant + new test `it ships skill 'references/' subtree alongside SKILL.md` covering all 6 agent target dirs. 65 tests pass, 271 assertions, was 257.)**

### Phase 3: Cross-reference existing skills (Priority: MEDIUM)

- [x] Add one-line pointer to `readme` and `release-notes` skills inside `#### 5a. README` section of `.ai/skills/pre-release/SKILL.md`. **(Done — added 3-paragraph pointer block after the existing "If unsure..." paragraph: canonical audit pattern owned by `readme` skill, release-notes drafting via `release-notes` skill, reference-review prompt per §8 cadence.)**
- [x] Append "See also: `readme` and `release-notes` skills (documentation generation)" line at end of **Authoring guidelines** section in `resources/boost/skills/package-development/SKILL.md`. **(Done — added a `### See also` subsection with both skills.)**
- [x] Add reference-review checkbox to `pre-release` skill per §8 maintenance cadence. **(Done — new row `5c. References` added to the Quick Reference matrix table; row `5a. README` updated to point at the `readme` skill's Audit pattern as the canonical source.)**
- [x] Re-sync, confirm targets updated. **(Done — sync wrote 96 skill dirs + 3 guideline files; drift check returned 96 unchanged, 3 unchanged on second run.)**
- [x] Tests — sync drift check passes. **(65 pest tests pass, 271 assertions; sync `--check` clean.)**

### Phase 4: Repo docs (Priority: MEDIUM)

- [x] Add `readme` and `release-notes` bullets to `README.md` shipped-skills list. **(Done — added two bullets after the existing `package-development` and `lean-dist` lines, matching the "Ships a X skill that …" pattern.)**
- [x] Do NOT touch `CHANGELOG.md` (auto-updated on release per `CLAUDE.md:116`). When this work ships in a release, write the release body using the new `release-notes` skill — `update-changelog.yml` will prepend it to CHANGELOG. **(Confirmed — CHANGELOG.md untouched.)**
- [x] Tests — none.

### Phase 5: Lint & release prep (Priority: LOW)

- [x] Run the `pre-release` skill end-to-end (`.ai/skills/pre-release/SKILL.md`). It is the **only** canonical verification source for this phase — including matrix-style `composer update --prefer-lowest|--prefer-stable` runs, the README/synced-doc audit, and the matrix-table pass criteria. Do NOT summarize, subset, or paraphrase its checklist here; follow it as written at the time of completion. **(Done — see Phase 5 Findings entry below for the matrix outcome. `prefer-lowest`/`prefer-stable` runs skipped per the skill's own gate: changes are markdown-only + a Pest dataset addition, not version-sensitive.)**
- [x] Tests — `pre-release` skill's matrix table all-green per its own pass criteria. **(All 7 matrix rows green: Rector 0 changed, Pint clean, PHPStan 0 errors, Pest 119 pass / 395 assertions / 0 failures, README audit clean, Boost docs drift-check clean, references current.)**

---

## Open Questions

None.

---

## Resolved Questions

1. **What replaces the abandoned `package-types` classification engine?** **Decision:** Two guidance skills (`readme`, `release-notes`) with a generic body and ecosystem-specific reference files (plain markdown, advisory pointer in SKILL.md). **Rationale:** User feedback mid-implementation: classification of package shapes belongs in a project-skeleton tool, not in package-boost. The actual goal is helping maintainers write good READMEs and release notes — a documentation problem, not a taxonomy problem. Reference-file pattern keeps Laravel specifics available without baking detection into the skill.

4. **Audit pattern: single source of truth?** **Decision:** `readme` skill (shipped to consumers) owns the canonical README-staleness audit pattern. `pre-release` skill (host-internal at `.ai/skills/pre-release/SKILL.md`, NOT shipped via `resources/boost/skills/`) cross-references it. **Rationale:** `pre-release` lives in `.ai/skills/` — package-boost's own release workflow — and never propagates to downstream consumer packages. Only the `readme` skill reaches them. The pattern therefore HAS to live in `readme`; anywhere else is unreachable. Drift risk between the two locations is internal-only and contained to this repo.

3. **Single combined skill instead of two?** **Decision:** Separate `readme` and `release-notes` skills. **Rationale:** Trigger sharpness > deduplication. Reference-file overlap between README and release-notes domains is small (section ordering ≠ severity-driven format). A combined `docs` skill would invite scope creep into SECURITY.md / CONTRIBUTING.md before v1 ships, and a fuzzy trigger surface risks the agent matcher activating on the wrong sub-task.

2. **Reference-loading mechanism: structured matcher or prose conditional?** **Decision:** Prose conditional in SKILL.md body. No `load_when` frontmatter, no matcher classes, no glob algorithm, no recipe, no helper. Reference files are pure markdown; SKILL.md says "if `composer.json`'s `require` has any `illuminate/*`, also apply `references/laravel-package.md`." **Rationale:** v1 ships one reference per skill — schema/algorithm machinery is overkill at that scale and reproduces the over-engineering trap that killed the prior `package-types` spec. The agent reading SKILL.md interprets the conditional like every other instruction. Worst-case failure is generic-only guidance, identical to the no-reference baseline. Codex re-review (round 2) flagged that any structured matcher must be testable; the simplest way to make it testable is to remove it. Add structure only when ≥3 references exist or trigger conditions become genuinely ambiguous in prose.

## Findings

### Phase 1 (2026-05-05)

Surveyed 11 README files + 5 GitHub release bodies. Both my pre-survey draft and the survey itself feed Phase 2 reference content; survey is authoritative where they disagree. **Several pre-survey assumptions were wrong** — flagged below.

#### A. README findings

**Two distinct README shapes in the wild — pick deliberately:**

1. **Stub READMEs** (~36–50 lines) — minimal, defer all docs to an external site. First-party Laravel packages use this almost universally (`laravel/socialite`: 36 lines, `laravel/horizon`: 38 lines, `beyondcode/laravel-dump-server`: 49 lines). Section sequence is canonical: Introduction → Official Documentation → Contributing → Code of Conduct → Security Vulnerabilities → License. Centered logo + badges row above first H2.

2. **Comprehensive READMEs** (~88–525 lines) — full docs in-README. Used by most Spatie/community packages and ecosystem plugins. Section sequence varies but a **closing-matter spine** is consistent: Testing → Changelog → Contributing → Security → Credits → License (sometimes preceded by Postcardware / Alternatives / Star History).

The reference file should teach both shapes and help authors pick.

**Spatie pattern: "Support us" first.** In 4 of 5 surveyed Spatie packages, `## Support us` (banner image + paid-products CTA) is the **first H2**, before installation. `spatie/laravel-data` is only outlier — leads with a video pitch, then "Support us" second. This is heritage, not best-practice — reference should mention the pattern but not prescribe it for non-Spatie packages.

**Code-first hero example.** Several comprehensive READMEs (`spatie/laravel-medialibrary`, `spatie/laravel-data`, `spatie/laravel-permission`) put a working code block **above the first H2**, before any prose. Strong, repeatable pattern. Reference should call this out as a recommended idiom for packages where one-glance usage is feasible.

**Version matrix is plugin-specific, not universal.** My pre-survey draft over-emphasized "always include a PHP × Laravel matrix table." Reality: most surveyed Spatie packages just say "Using an older version of PHP / Laravel?" and link to docs. Only `awcodes/filament-curator` ships a real Compatibility table — and it's a Filament-major × package-major matrix, not PHP × Laravel. **Recommendation:** version matrix is a load-bearing first-class section for ecosystem plugins crossing major boundaries (Filament/Livewire/Nova). For other packages, a one-line "supports Laravel X-Y" suffices.

**GitHub admonitions are widespread.** `> [!WARNING]`, `> [!NOTE]`, `> [!IMPORTANT]` blocks used heavily in `awcodes/filament-curator` (throughout install/upgrade flow) and `barryvdh/laravel-debugbar` (two opening warnings about security/performance). Reference should include this as a tool, especially for security/perf caveats and upgrade notes.

**Filament plugin convention: Compatibility + Upgrading H2s before Installation.** Curator does this. Reference's Filament-plugin sub-section (per §11) should encode it.

**Self-promo at the end is a pattern.** `wire-elements/spotlight` closes with a "Beautiful components crafted with Livewire" promo block after License. Different placement from Spatie's pre-install pitch. Reference can mention both placements and let authors choose.

**Recommended outline for `readme/references/laravel-package.md`** (~100 lines):
1. Pick a shape: stub (defer to docs site) vs comprehensive (in-README).
2. Stub-shape canonical section list (6 H2s, ~36 lines target).
3. Comprehensive-shape recommended section sequence — title + tagline + badges → optional code-first hero → optional sponsorship pitch → install → config → usage → advanced features → testing → closing matter spine.
4. Closing-matter spine (mandatory ordering).
5. Version-matrix guidance: when to ship a table (ecosystem plugins crossing majors), when "supports Laravel X-Y" suffices.
6. Auto-discovery note (Laravel 5.5+; explicit registration instructions are stale).
7. Testbench testing snippet.
8. GitHub admonitions tool kit.
9. Cross-references: `lean-dist` for `.gitattributes`, `release-notes` skill for tag-time work.

#### B. Release-notes findings

**Major surprise: surveyed release bodies are MUCH simpler than my pre-survey draft assumed.** All 5 surveyed bodies have either `## What's Changed` (Spatie permission, Spatie data, Curator, Debugbar) or no headings at all (Horizon). **Zero use of "### Added/Fixed/Changed/Removed" subsections, zero "## Breaking changes" callouts, zero upgrade-path code blocks** in this sample.

Universal pattern in the sample:
- One H2 (`## What's Changed`) or no headings.
- Each bullet links the PR (`title by @author in <PR url>`).
- Trailing `**Full Changelog**:` compare link to previous tag (4 of 5; Horizon omits).
- Length: 2–6 lines for these patch releases.

**This matches GitHub's "Generate release notes" auto-output exactly.** Most maintainers are accepting GitHub's defaults rather than hand-crafting structured release bodies. Sample is patch-heavy — major-release bodies likely look different but were not surveyed.

**Implication for the `release-notes` reference file:** Don't over-prescribe a severity-driven, multi-section format that real Spatie/Laravel/Filament packages don't use. Instead:
- Acknowledge the GitHub auto-format as the **default**: PR-linked bullets + Full Changelog footer.
- Teach when to override the default — only for major releases or when commits don't speak for themselves.
- For overrides, recommend minimal added structure: a one-line summary above the auto-bullets, optional "Breaking changes" subsection ONLY when there is one, optional "Upgrade guide" link.
- Conventional-commits derivation: rare in this sample (only Curator uses `Chore(deps):` / `Fix:` prefixes, and even then no subgrouping). Mention as opt-in, not standard.

**Recommended outline for `release-notes/references/laravel-package.md`** (~80 lines):
1. Default = GitHub auto-generated format. Use it for patch and most minor releases.
2. When to override (major releases, breaking changes, multi-PR features that need a narrative).
3. Override structure: one-line summary → optional Highlights (≤3 user-facing bullets) → optional Breaking changes (only when real) → auto-bullet list of PRs → Full Changelog footer.
4. Voice: lead with impact, not refactor terminology.
5. Laravel-package specifics: minimum-version bumps go in Breaking changes; ecosystem-plugin (Filament/Livewire/Nova) version constraint shifts deserve a mention.
6. CHANGELOG interplay (this repo specifically): GitHub release body becomes the CHANGELOG entry verbatim per `update-changelog.yml`.
7. Anti-patterns observed in adjacent ecosystems but absent from Laravel/Spatie sample: severity-driven multi-section format with "Added/Changed/Removed/Fixed" subsections. Mention as an option but don't make it the default.

#### C. Phase 2 implications

- Resist the urge to over-structure either reference. Real package READMEs and release bodies are **simpler than I drafted from training-knowledge alone**. Match observed practice; don't invent ceremony.
- Survey limitation: only 5 release bodies, all patch/minor. If Phase 2 reference content for major-release format proves contentious in PR review, expand the survey to 4–5 major releases in the same packages before re-iterating.
- Two findings deserve cross-linking from the reference into the live `readme` skill body: (a) "pick a shape" up-front decision, (b) GitHub admonitions toolkit. Both are short enough to inline in SKILL.md if §3's hard cap (160 lines) holds; otherwise keep in reference.

### Phase 2 (2026-05-05)

- Both skills landed under their cap (87 / 92 lines vs 160 / 140 caps). The Laravel reference for `release-notes` came in shorter than the 80–120 target band (67 lines) — drafting against the Phase 1 survey reality (most release bodies are minimal) made over-prescribing feel forced. Left as-is rather than padding.
- Both findings flagged in the Phase 1 outline did get inlined into `readme/SKILL.md`: the "pick a shape first" section and GitHub admonitions appear in the generic body, not just the Laravel reference. Generic enough to apply outside Laravel.
- `SyncWriter::linkOrCopy` behavior held exactly as predicted in §1 — the `references/` subtree propagated to all 6 unique agent target dirs without code change. The new sync test pins this for future refactors.
- Phase 2 task said hard cap 160 / 140; actual sizes left ~70+ lines of headroom. Future reference-content additions can land without restructuring SKILL.md.
- One observation worth carrying into Phase 3: the cross-refs section in `readme/SKILL.md` already points at `release-notes`, `lean-dist`, and `package-development`. So the Phase 3 task adding "See also: readme + release-notes" to `package-development/SKILL.md` is the symmetric pair — both directions wired.

### Phase 5 (2026-05-05)

`pre-release` skill matrix run end-to-end:

| Step           | Outcome                                                        |
|----------------|----------------------------------------------------------------|
| 1. Rector      | OK, 0 files changed                                            |
| 2. Pint        | passed                                                         |
| 3. PHPStan     | 0 errors                                                       |
| 4. Pest (full) | 119 pass / 395 assertions / 0 failures                         |
| 5a. README     | audit pattern applied — new bullets accurate, no stale claims  |
| 5b. Boost docs | sync drift-check clean (96 skills, 3 guidelines unchanged)     |
| 5c. References | both `references/laravel-package.md` files current vs ecosystem |

`composer update --prefer-lowest|--prefer-stable` matrix runs skipped per the pre-release skill's own gate ("If changes touch anything version-sensitive, also run …"). Changes in this branch are markdown-only (4 new docs + 3 edited skill bodies + 2 README bullets) plus a Pest dataset addition — none version-sensitive. CI matrix will exercise all cells regardless.

One transient: Pest's `wipeArtifacts()` deletes `CLAUDE.md` / `AGENTS.md` / `GEMINI.md` between test runs, which made an early `package-boost:sync --check` report drift. Re-running plain sync first then `--check` shows the steady state — clean. Worth noting for future Phase 5 runs after a test pass.

<!-- Notes added during implementation. Do not remove this section. -->
