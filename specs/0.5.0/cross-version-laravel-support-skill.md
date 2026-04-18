# Cross-Version Laravel Support Skill

## Overview

Ship a dedicated skill describing how package authors support multiple
Laravel / PHP majors at once — composer constraint reading,
version-guard patterns, feature detection, and the local verification
workflow. Both reviewing peers flagged this as generic work they keep
re-explaining to Claude. Ship it once as
`resources/boost/skills/cross-version-laravel-support/SKILL.md`.

This skill pairs with `ci-matrix-troubleshooting` (separate spec).
Trigger partitioning between the two is defined below to avoid
overlapping activation.

---

## 1. Current State

No shipped skill covers cross-version compatibility. The shipped
`package-development` SKILL contains a short paragraph that says
"check `composer.json`, don't use version-exclusive features" — true
but abstract; doesn't tell the reader what to actually check, how to
guard, or how to verify locally.

## 2. Proposed Changes

### Ownership of the foundation's cross-version paragraph

**This spec owns the trim.** The sibling `runner-agnostic-foundation`
spec does not touch the Cross-Version Compatibility section. When
this spec lands:

1. Trim the foundation's "Cross-Version Compatibility" section to a
   single paragraph: "Supporting multiple Laravel / PHP majors is
   routine for packages. Activate the
   `cross-version-laravel-support` skill when adding version-
   sensitive code or diagnosing compatibility issues."
2. Move the existing guidance (check `composer.json`, avoid
   version-exclusive APIs, keep CI green) into the new skill.

### Trigger partition vs `ci-matrix-troubleshooting`

Overlapping keywords (`prefer-lowest`, `prefer-stable`, `testbench`)
would cause non-deterministic activation. Partition by intent:

| Trigger type | Activates |
|---|---|
| "am I using / should I add something that works across versions?" | `cross-version-laravel-support` |
| "CI matrix just went red — why?" | `ci-matrix-troubleshooting` |

Concrete descriptors:

- **Cross-version triggers:** "composer constraint", "illuminate/
  support version", "laravel version matrix", "backwards
  compatibility", "version guard", "feature detection", "minimum
  laravel version", "support older laravel".
- **CI troubleshooting triggers (owned by sibling spec):** "ci
  matrix fail", "prefer-lowest fail", "matrix red", "dependency
  conflict", "composer resolve", "version excluded".

Keywords `prefer-lowest` / `prefer-stable` stay in both descriptions
— they appear in both flows — but the longer phrase context
disambiguates.

### Skill body outline

1. **When to activate** — preventive (adding version-sensitive
   code); diagnostic (understanding existing constraint setup).
2. **Reading `composer.json` constraints** — how to decode
   `^11.0||^12.0||^13.0` into concrete minimum and maximum ranges.
3. **Version-guard patterns** — `version_compare(app()->version(),
   '12.0', '>=')`, `class_exists` + `method_exists` feature
   detection, conditional trait composition.
4. **Local verification workflow** — the three-step run (default /
   `--prefer-lowest` / `--prefer-stable`) against the host's test
   runner.
5. **Deprecation-but-not-removed trap** — APIs marked `@deprecated`
   in newer Laravel still work; don't rush the upgrade unless the
   minimum version has moved.
6. **Cross-link to `ci-matrix-troubleshooting`** — "if CI actually
   broke, activate `ci-matrix-troubleshooting` instead."

## Implementation

- [ ] `resources/boost/skills/cross-version-laravel-support/SKILL.md`
  with frontmatter (`name`, `description` carrying the trigger
  vocabulary above) and body per outline. Reference
  `resources/boost/skills/package-development/SKILL.md` for the
  frontmatter shape.
- [ ] Trim `resources/boost/guidelines/foundation.md` Cross-Version
  section to the single-paragraph pointer above.
- [ ] Smoke-test the skill against a pool of real matrix-drift
  scenarios the project itself has hit during CI history (check
  `gh run list --workflow=run-tests.yml --status=failure --limit=20`
  and map each failure to which skill workflow would have helped).
- [ ] Update `tests/SyncCommandTest.php`: the existing "syncs user
  and shipped skills" assertion checks specific names. Extend it to
  assert the new SKILL.md lands in `.claude/skills/cross-version-
  laravel-support/`. See the cross-cutting test-count concern in
  the sibling `ci-matrix-troubleshooting-skill.md` spec — whichever
  lands first introduces the shared `SHIPPED_SKILLS` constant.
- [ ] Document migration impact: downstream users running
  `--check` see drift on skills (new skill added) and guidelines
  (foundation section trimmed). Expected one-time.
- [ ] Prune the corresponding entry from `ROADMAP.md`.

### Files

| File | Change |
|------|--------|
| `resources/boost/skills/cross-version-laravel-support/SKILL.md` | **New** — shipped skill |
| `resources/boost/guidelines/foundation.md` | Trim Cross-Version section to a pointer |
| `tests/SyncCommandTest.php` | Assert new skill present after sync |
| `ROADMAP.md` | Prune from 0.5.0 list |

---

## Open Questions

1. **Concrete code snippets or abstract guidance?** One canonical
   example per version-guard pattern, not a full cookbook. Readers
   grep for the pattern in-repo if they need more.

2. **Minimum Laravel floor across this skill's guidance.** The
   skill covers packages supporting 11.x–13.x. If the project
   floor shifts (e.g. drop 11 in a future major), the skill needs
   a version note. Cheap fix: reference `composer.json` directly
   rather than hardcoding versions in the skill body.

---

## Findings

<!-- Notes added during implementation. Do not remove this section. -->
