---
name: ai-guidelines
description: "MUST USE when editing this repo's AI guidelines or deciding where a new skill belongs. Repo-specific guardrail: never edit auto-generated files; pick the right source dir (`.ai/skills/` internal vs. `resources/boost/skills/` shipped); always re-sync after `.ai/` edits. For skill-authoring mechanics (frontmatter, naming, namespacing), defer to the shipped `skill-authoring` skill. Activates when: editing any file in `.ai/`, editing any guideline under `.ai/guidelines/`, choosing where a new skill lives in this repo, editing any auto-generated guideline / skill dir (`CLAUDE.md`, `AGENTS.md`, `GEMINI.md`, `.claude/skills/`, `.cursor/skills/`, `.agents/skills/`, `.github/skills/`, `.junie/skills/`, `.kiro/skills/`), or user mentions: guidelines, CLAUDE.md, AGENTS.md, GEMINI.md, AI configuration, sync."
---

# AI Guidelines & Skills (Repo-Specific)

This repo *is* package-boost, so it has a dual structure that
downstream consumers don't:

- `.ai/skills/` and `.ai/guidelines/` — **internal**. Drive the
  per-agent files for this repo only.
- `resources/boost/skills/` and `resources/boost/guidelines/` —
  **shipped**. Distributed to every package that installs
  package-boost.

Anything authored under `resources/boost/` lands in arbitrary
downstream repos; anything under `.ai/` stays here.

For general skill-authoring mechanics (frontmatter, naming,
namespacing across host / vendor / package-boost defaults, choosing
between `.ai/skills/` and `resources/boost/skills/`), use the shipped
**`skill-authoring`** skill — it covers the full picture and is the
same guidance consumers see.

## Never Edit Generated Files

`CLAUDE.md`, `AGENTS.md`, `GEMINI.md`, and the per-agent skill
directories — `.claude/skills/`, `.cursor/skills/`,
`.agents/skills/`, `.github/skills/`, `.junie/skills/`,
`.kiro/skills/` — are **auto-generated** by `package-boost:sync`
from `.ai/` + `resources/boost/`. Direct edits get overwritten on
the next sync.

If you're about to edit one of those, stop and find the source under
`.ai/` (or `resources/boost/` for shipped skills) instead.

## Picking the Source Directory in This Repo

| Content | Path | Audience |
|---|---|---|
| Skill used only when working on this repo | `.ai/skills/{name}/SKILL.md` | Internal contributors |
| Skill shipped to every package-boost consumer | `resources/boost/skills/{name}/SKILL.md` | Downstream packages |
| Guideline used only here | `.ai/guidelines/*.md` or `*.blade.php` | Internal contributors |
| Guideline shipped to every consumer | `resources/boost/guidelines/*.md` | Downstream packages |

Most internal-only tooling skills (`code-review`, `evaluate`,
`pre-release`, etc.) belong in `.ai/skills/`. Reach for
`resources/boost/skills/` only when consumers should receive the
content.

## Authoring Guidelines

Edit files in `.ai/guidelines/`:

- **Markdown files** (`*.md`): standard markdown.
- **Blade files** (`*.blade.php`): wrap code examples in
  `@verbatim` blocks so PHP / Blade syntax doesn't render.

Filename controls ordering (alphabetical across the source dir).

## Sync Workflow

**Always run `package-boost:sync` after editing any file under
`.ai/` or `resources/boost/`.** That regenerates the per-agent
files locally so you're working against current content.

```bash
vendor/bin/testbench package-boost:sync
```

Verify cleanly:

```bash
vendor/bin/testbench package-boost:sync --check
```

Exits non-zero when generated files diverge from sources.

### Generated Files Are NOT Committed in This Repo

Important repo-specific exception, set by
`.ai/guidelines/release-automation.md` (always loaded into
`CLAUDE.md`): the generated outputs (`CLAUDE.md`, `AGENTS.md`,
`GEMINI.md`, every per-agent skill dir) are **gitignored** because
the sync-command tests under `tests/` exercise the same filesystem
paths and would clobber any committed copies on every Pest run. So:

- `git status` should show only the `.ai/` or `resources/boost/`
  source change. Generated dirs not appearing is correct.
- Don't fight the gitignore — re-sync is run locally on demand.
- Downstream packages that consume package-boost work the opposite
  way (they commit the generated files); the shipped
  `skill-authoring` skill describes that flow for them.

## Checklist

- [ ] Edit only files under `.ai/` or `resources/boost/` — never the
      generated outputs.
- [ ] If creating or renaming a skill, follow the shipped
      **`skill-authoring`** skill (frontmatter, naming, namespacing).
- [ ] Run `vendor/bin/testbench package-boost:sync`.
- [ ] `git status` shows the source change only — no generated dirs
      should appear (they're gitignored).
