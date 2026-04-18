# CI Matrix Troubleshooting Skill

## Overview

Ship `resources/boost/skills/ci-matrix-troubleshooting/SKILL.md` —
activated when a package's CI matrix goes red, with a checklist of
common causes and how to diagnose each. Driven by concrete pain from
the laravel-js-store peer: a full session debugging a
`roave/security-advisories` PHPUnit-floor bump with no shipped
guidance anywhere.

Pairs with `cross-version-laravel-support`; trigger partition
defined in that spec is authoritative.

---

## 1. Current State

No skill covers CI matrix debugging. A fresh Claude session opening a
red-matrix repo has to reason from scratch every time: read
`.github/workflows/run-tests.yml`, run
`composer update --prefer-lowest` locally, cross-reference
Testbench/PHPUnit compatibility tables. The domain knowledge is
recurring and memorizable.

## 2. Proposed Changes

### Trigger partition (recap)

Per the sibling `cross-version-laravel-support-skill.md` spec:

| Descriptor set (owned here) |
|---|
| "ci matrix fail", "matrix red", "prefer-lowest fail", "dependency conflict", "composer resolve", "version excluded", "security-advisories floor", "testbench phpunit interlock", "matrix cell regression" |

`prefer-lowest` / `prefer-stable` / `testbench` also appear in the
sibling skill's descriptors — shared vocabulary. Longer trigger
phrases disambiguate.

### Body outline

1. **When to activate** — any CI matrix run showing red, or "why
   does prefer-lowest break" phrasing.
2. **Diagnostic step 1: reproduce locally** —
   ```
   composer update --prefer-lowest --prefer-dist --no-interaction
   # run the project's test runner (pest or phpunit)
   ```
   Then repeat with `--prefer-stable`.
3. **Usual suspects** — short, pattern-focused list (shape of
   failures rather than a versioned incident log):
   - Transitive dep (commonly `roave/security-advisories`) bumped
     a floor.
   - Testbench version pinned to a PHPUnit range the matrix cell
     can't reach.
   - Package `require` floor doesn't actually install on the
     declared minimum.
   - phpstan/larastan floor incompatible with older Laravel.
   - API / trait / facade that only exists on one side of the
     supported range (cross-link to
     `cross-version-laravel-support`).
4. **Diagnostic step 2: `composer why-not`** —
   `composer why-not vendor/package 1.2.3` traces which constraint
   forbids the desired version.
5. **Fix patterns** — widen constraint; bump package's own floor
   if the feature is genuinely required; exclude the matrix cell
   with a comment explaining why; add a `conflict` entry when a
   security advisory's floor is too aggressive.
6. **When to file upstream** — how to distinguish "our constraint
   is wrong" from "a dependency ships an over-eager floor" and
   what makes a useful bug report.

## Implementation

- [ ] `resources/boost/skills/ci-matrix-troubleshooting/SKILL.md`
  with trigger-word-heavy `description` per the partition table.
- [ ] Introduce a shared `SHIPPED_SKILLS` constant in
  `tests/SyncCommandTest.php` listing the expected shipped skill
  directory names; have the new test assert that every entry
  shows up under `.claude/skills/`. This addresses the cross-
  cutting "expected-skill-count brittleness" — whichever skill
  spec lands first introduces the constant; the other just
  appends.
- [ ] Cross-link bidirectionally with
  `cross-version-laravel-support/SKILL.md`.
- [ ] Smoke-test against the project's own CI failure history
  (`gh run list --workflow=run-tests.yml --status=failure
  --limit=20`) — for each failure, confirm the skill's checklist
  would have surfaced the cause without extra prompting.
- [ ] Document migration impact: downstream users running
  `--check` see drift on skills (new skill added). Expected
  one-time.
- [ ] Prune the corresponding entry from `ROADMAP.md`.

### Files

| File | Change |
|------|--------|
| `resources/boost/skills/ci-matrix-troubleshooting/SKILL.md` | **New** — shipped skill |
| `resources/boost/skills/cross-version-laravel-support/SKILL.md` | Bidirectional cross-link (if sibling lands first) |
| `tests/SyncCommandTest.php` | Extend / introduce `SHIPPED_SKILLS` + assertion |
| `ROADMAP.md` | Prune from 0.5.0 list |

---

## Open Questions

1. **Scope creep.** CI matrix debugging can absorb unlimited
   framework-specific knowledge. Cap this skill at first-ten-
   minutes triage; link out to Laravel / Testbench docs for deep
   cases.

2. **Canonical incident list vs shape-only.** Versioned incident
   logs go stale. Prefer describing failure shapes (how to
   recognise each) over a list of historical bumps.

---

## Findings

<!-- Notes added during implementation. Do not remove this section. -->
