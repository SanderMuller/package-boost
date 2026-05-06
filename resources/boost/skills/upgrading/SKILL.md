---
name: upgrading
description: "Helps maintainers write UPGRADING.md / UPGRADE.md files — the canonical source of truth for migration steps between major versions. Teaches per-major sections, before/after code, version-jump rules. Points to `references/laravel-package.md` for ecosystem conventions. Activates when: writing UPGRADING.md, drafting an upgrade guide, planning a major version bump, or user mentions UPGRADING, upgrade guide, migration steps."
---

# Upgrading

Generic guidance for writing and maintaining upgrade guides. Ecosystem-specific conventions (filename variants, framework patterns, automated upgrade tooling) live in the `references/` subdirectory.

## Reference pointer

Working on a Laravel package? Also apply `references/laravel-package.md` from this skill directory — it covers the four observed filename conventions, version-comment-labelled before/after style, impact-tagging idioms (Spatie/Laravel/Filament), and stable-anchor discipline for release-notes link targets.

Heuristic: it's a Laravel package if `composer.json`'s `require` lists `laravel/framework`, any `illuminate/*`, or any `filament/*` / `livewire/*` / `laravel/nova` ecosystem package. Pure-PHP libraries skip the reference and use generic guidance.

## When you need an upgrade guide

Write or update one for **every breaking release**, regardless of package size. A breaking release is any version that:

- Bumps a major version number (X.Y.Z → (X+1).0.0).
- Removes a public API (class, method, command signature, config key).
- Renames a public symbol without keeping the old name as a deprecated alias.
- Bumps the minimum PHP or Laravel version.
- Changes default behavior in a way callers will notice.

For minor/patch releases with no breaking changes, do not update the upgrade guide.

The "tiny package — inline migration steps in the release body" exception is rejected: it conflicts with the contract that release-notes' `## Breaking changes` bullets link to UPGRADING.md anchors. Even a 10-line UPGRADING.md beats inlining steps in the release body.

## File structure

- One `# Upgrade Guide` H1.
- One H2 per major-version transition: `## Upgrading from v4 to v5`. **Reverse-chronological** — newest at top, so existing users find their starting point first.
- Each transition section is self-contained — assume the reader is on the immediately preceding major.

H2 anchor IDs are load-bearing. GitHub auto-generates kebab-case anchors from headings (`## Upgrading from v4 to v5` → `#upgrading-from-v4-to-v5`). Release-notes' `## Breaking changes` bullets link to these anchors via `[UPGRADING.md#anchor]`. **Do not rename H2s after release** — the links break and there is no automated check.

Optional: a "Upgrade Essentials" preamble H2 above all transitions, listing universal pre-flight steps (back up the database, check requirements, run tests). Useful when the same setup applies across all version transitions. Spatie laravel-permission uses this pattern.

## Per-major section structure

Within each `## Upgrading from vN to vN+1` section, use **topical H3s** for what changed:

- Requirements (PHP, Laravel, ecosystem deps)
- Composer constraint update
- Configuration changes
- Renamed methods / classes
- Removed features
- Behavior changes

Drop H3s that don't apply to this transition — empty sections are noise.

For long sections (the change list runs past ~20 lines), adopt **impact tagging**: prefix each H3 or bullet with `Likelihood Of Impact: High|Medium|Low`. Helps readers triage what affects them. Used by Laravel framework, spatie/laravel-data, and Filament — recommended for any guide section over ~200 lines.

## Version-jump rules

If a user is upgrading across multiple majors (v3 → v5), they must complete the intermediate transitions in order. **State this explicitly** at the top of every transition section that has a predecessor:

> Upgrading from v3? Complete [Upgrading from v3 to v4](#upgrading-from-v3-to-v4) first, then return here.

Surveyed packages don't ship this rule explicitly — they imply it via reverse-chronological ordering. Adding it is a quality improvement that prevents the most common upgrade-mistake (skipping a major).

## Before/after code style

Show migration code as **two adjacent fenced blocks** with version-comment labels at the top of each:

```php
// v3
$model->setMedia('cover')->preservingOriginal()->save();
```

```php
// v4
$model->addMedia('cover')->preservingOriginal()->toMediaCollection();
```

Show only the lines that change, plus 1–2 of context. **No git-diff syntax for migration code** — none of the surveyed Laravel packages use it for PHP/runtime examples. (Diff syntax for config-file constraint updates like `composer.json` is fine — it's the clearest way to show a single-line edit. The Laravel reference uses this for the composer constraint section.) **No full-file dumps** — the reader is migrating a working codebase, not learning the API from scratch.

For non-code changes (config keys, environment variables), prose + a single code block suffices.

## Audit pattern (canonical)

Run when adding a new major-transition section, or when reviewing the guide pre-release.

1. Re-run the migration steps against a fresh fixture project. The guide is correct only if a clean clone + the listed steps produces a working upgrade.
2. Verify every `composer.json` constraint example matches the actual `composer.json` after upgrade.
3. Check H2 anchors against the release-notes' `## Breaking changes` bullet links — every `[UPGRADING.md#anchor]` link must resolve.
4. Search release-notes / CHANGELOG for any breaking change not covered by an UPGRADING.md section. Add it.
5. Check that older transition sections (v2→v3, v1→v2) still render correctly — anchors mustn't have shifted.

## Bookwork to cut

UPGRADING.md is operational. Cut anything that doesn't serve a reader actively running the upgrade:

- **Rationale prose** ("we changed X because Y") — belongs in release notes, not migration steps.
- **Historical context** ("originally we did it this way, then in v3 we…") — readers don't care about prior versions.
- **Full-file dumps** in before/after — show only the changed lines plus minimal context.
- **"Why we made this change"** sections — same anti-pattern as release-notes bookwork.
- **Inline links to PRs/issues for every change** — link only when the PR explains a non-obvious migration choice.

## Cross-refs

- `release-notes` skill — the release body's `## Breaking changes` bullets link to this file's H2 anchors.
- `readme` skill — the README's Upgrading section should link to this file.
- `lean-dist` skill — UPGRADING.md is a runtime doc, do not export-ignore it.
- `package-development` skill — Laravel package conventions.
