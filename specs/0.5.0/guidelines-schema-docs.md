# `.ai/guidelines/` Schema Docs

## Overview

The shipped `package-development` skill documents where `.ai/` lives
and how to sync it, but doesn't describe what a valid guideline file
looks like — no frontmatter spec, no ordering rules, no conflict
semantics. Downstream authors reverse-engineer from sibling files.
Add a short "Authoring guidelines" section to the shipped SKILL.

---

## 1. Current State

**File:** `resources/boost/skills/package-development/SKILL.md` lines
48–66.

Covers the `.ai/` directory layout, the `package-boost:sync` command,
and `--check` for CI.

Does not cover:

- Whether `.ai/guidelines/*.md` needs frontmatter (answer: no — it's
  plain markdown concatenated in filename order).
- File ordering (answer: `sortByName` across each source dir).
- Interaction between shipped foundation and user guidelines
  (answer: shipped first, `---` horizontal rule separator, user
  second).
- How to exclude a shipped guideline you don't want (answer: add
  its key to `excluded_boost_guidelines` in the published config;
  keys match Boost's `GuidelineComposer` conventions).
- Skill file shape (frontmatter fields, body format).

## 2. Proposed Changes

Append an `## Authoring guidelines` section to the shipped SKILL.
Keep it short — reference lookup, not a tutorial. Target 20–30 lines.

Subsections:

1. **File shape** — plain `.md`, no required frontmatter, one topic
   per file, filename controls ordering (`sortByName`).
2. **Rendering model** — shipped foundation renders first, `---`
   separator, then user guidelines in alphabetical order.
3. **Opting out of shipped content** — short note pointing to the
   README's "Customising excluded guidelines" section. Do not
   duplicate the config example.
4. **Skill file shape** (smaller subsection) — YAML frontmatter
   with `name` and `description`, body is markdown. The
   description's trigger vocabulary drives activation.

## Implementation

- [ ] Append `## Authoring guidelines` section to
  `resources/boost/skills/package-development/SKILL.md` per
  outline. Cross-link to the README's "Customising excluded
  guidelines" block; do not duplicate.
- [ ] Add an assertion in `tests/SyncCommandTest.php`'s existing
  "ships foundation guideline" test that the rendered block
  contains "Authoring guidelines" — cheap guard against silent
  section removal.
- [ ] Document migration impact: downstream users running
  `--check` see drift on skills (SKILL.md content changed).
  Expected one-time.
- [ ] Prune the corresponding entry from `ROADMAP.md`.

### Files

| File | Change |
|------|--------|
| `resources/boost/skills/package-development/SKILL.md` | Append `## Authoring guidelines` section |
| `tests/SyncCommandTest.php` | Assert new section present after sync |
| `ROADMAP.md` | Prune from 0.5.0 list |

---

## Open Questions

1. **Do we want frontmatter on guideline files later?** Adding a
   `description` / `priority` / `category` field would enable
   smarter ordering and filtering. Out of scope for this doc
   spec; track in ROADMAP if guideline counts per package grow
   past a dozen.

---

## Findings

<!-- Notes added during implementation. Do not remove this section. -->
