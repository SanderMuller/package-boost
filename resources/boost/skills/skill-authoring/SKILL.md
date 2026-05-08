---
name: skill-authoring
description: "MUST USE when authoring an AI skill — creating a new SKILL.md, naming a skill, or deciding where one lives. Covers namespacing across host/vendor/shipped skills, auto-activating frontmatter, `.ai/skills/` vs `resources/boost/skills/` choice, and `package-boost:sync` regen. Activates: creating a skill, adding a skill, drafting a SKILL.md, naming a skill, choosing where a skill lives, editing `.ai/skills/**/SKILL.md` or `resources/boost/skills/**/SKILL.md`; mentions: skill, SKILL.md, skill name, skill namespace, skill collision, vendor skill, shipped skill."
---

# Skill Authoring

Guidance for adding a new AI skill to a package that uses
package-boost. Covers the four things models routinely get wrong:
**name collisions**, **dead descriptions**, **wrong source
directory**, and **forgotten sync**.

This skill is about `SKILL.md` authoring only. For
`.ai/guidelines/*.md` content, see the `package-development` skill's
*Authoring guidelines* section.

## When to use this skill

- Creating a new skill in a package (host repo or vendor package).
- Renaming an existing skill.
- Reviewing a skill's frontmatter or location before merging.

## 1. Namespace the skill name

Skills merge in load order, and **later entries silently override
earlier ones on name collisions**:

1. Package-boost's shipped defaults
2. Vendor packages, alphabetical by `vendor/name`
3. Host `.ai/`

That ordering produces three real collision modes:

| Mode | Example | Effect |
|---|---|---|
| **Host masks vendor** | Host adds `.ai/skills/billing-audit/`, vendor `acme/billing` ships `resources/boost/skills/billing-audit/` | Vendor's skill never loads. No warning. |
| **Vendor masks vendor** | `acme/billing` and `widgets/co` both ship `resources/boost/skills/migrate/` | Vendors load alphabetically and later entries overwrite earlier ones, so `widgets/co` wins; `acme/billing`'s loses. No warning. |
| **Vendor masks package-boost default** | Vendor ships `resources/boost/skills/readme/` | Vendor overrides package-boost's shipped `readme` skill in every consumer. Easy to do by accident. |

Avoid all three by prefixing:

- `{vendor}-{topic}` — e.g. `acme-billing-audit`, `widgets-migrate`.
- `{package}-{topic}` — e.g. `nova-fields-validate`,
  `livewire-volt-recipes`.

**Required for vendor-shipped skills.** Recommended for host skills
that overlap with anything common.

### Reserved shipped-skill names

Package-boost itself ships these; do not name a vendor or host skill
the same unless you intend to override:

- `ci-matrix-troubleshooting`
- `cross-version-laravel-support`
- `lean-dist`
- `package-development`
- `readme`
- `release-notes`
- `skill-authoring`
- `upgrading`

Run `ls vendor/sandermuller/package-boost/resources/boost/skills/`
in your project to see the current list.

## 2. Pick the right source directory

| Audience | Path | Notes |
|---|---|---|
| Skill for *your own repo* | `.ai/skills/{name}/SKILL.md` | Stays local; not shipped to your package's consumers. |
| Skill *shipped to your package's consumers* | `resources/boost/skills/{name}/SKILL.md` | Picked up by package-boost in every consumer that installs your package. **Namespacing mandatory.** |

Generated outputs in per-agent directories (`.claude/skills/`,
`.cursor/skills/`, `.agents/skills/`, `.github/skills/`,
`.junie/skills/`, `.kiro/skills/`) are written by
`package-boost:sync`. **Never edit those directly** — your edits get
overwritten on the next sync.

Directory name must be kebab-case and match the `name` field in
frontmatter exactly.

## 3. Write a description that actually triggers

The frontmatter `description` is the trigger surface for
auto-activation **and** counts against a global skill-description
budget — every word ships in every host's prompt. A vague description
("Helps with reviews") never matches; a bloated one wastes tokens.
Aim for specific *and* terse, **in that order**.

**Triggers come first.** When trimming an existing description, never
remove or compress a user-facing trigger phrase to save tokens.
Trim the surrounding prose instead. Concretely:

- Keep every trigger phrase verbatim — do not abbreviate
  (`backwards compatibility` not `backwards compat`,
  `minimum laravel version` not `min laravel version`).
- Do not collapse distinct phrases into slash-shorthand
  (`.claude in tarball, CLAUDE.md in tarball` — not
  `.claude/CLAUDE.md in tarball`). Users type one or the other,
  not the bundle.
- Avoid wildcards in trigger phrases (`illuminate/support version`
  not `illuminate/* version`) unless the original full phrase is
  also present.

Required ingredients:

- **Lead with the action.** "Use when …" / "Reviews …". Drop
  articles and filler ("Helps maintainers", "any project", "for
  any").
- **Scenarios** the skill applies to.
- **Trigger phrases** after `Activates:` — natural-language phrases
  a user would type. Include verbs (`writing`, `drafting`,
  `auditing`) and nouns the user would actually use. Comma-separated.
  Split into `Activates: {scenarios}; mentions: {user phrases}` for
  readability.
- **Guardrail prefix.** For skills that must run before other work
  (collision-prevention, irreversible-edit safety), prefix with
  `MUST USE when …`.

Pattern:

```yaml
description: "Use when {trigger}. {Short what-it-covers, optional.}
Activates: {scenario 1}, {scenario 2}; mentions: {phrase 1},
{phrase 2}, {phrase 3}."
```

For a guardrail skill:

```yaml
description: "MUST USE when {trigger}. {What it prevents.} Activates:
{file-path triggers}, {scenarios}; mentions: {phrases}."
```

Trim rules (apply to prose, not triggers): no articles (a/an/the),
no filler (just/really/any), short synonyms (`covers` not `teaches
you about`), drop the project scope when implied.

Look at sibling shipped skills (`readme`, `upgrading`,
`release-notes`) for working examples.

## 4. Skill body structure

Minimum skeleton:

```markdown
---
name: my-skill
description: "…"
---

# My Skill

One-paragraph framing — what the skill is, what it isn't.

## When to use this skill

- Bullet list of concrete situations.

## {Topic 1}

Instructions.

## {Topic 2}

Instructions.
```

Optional `references/` subdirectory — drop ecosystem-specific or
deep-dive material there and link from the body. Used by the
`readme`, `release-notes`, and `upgrading` shipped skills for
Laravel-package-specific guidance. `package-boost:sync` propagates
the whole `references/` subtree alongside `SKILL.md`.

## 5. Run sync after every edit

```bash
vendor/bin/testbench package-boost:sync
```

This regenerates the per-agent skill directories. Skipping it leaves
agents reading stale content.

**Commit the generated files together with the source.** They ship
with the package and the `--check` mode will fail CI if they drift.

## Checklist

- [ ] Directory name is kebab-case.
- [ ] `name:` in frontmatter matches the directory name exactly.
- [ ] Name is namespaced (`{vendor}-{topic}` or `{package}-{topic}`)
      — required for `resources/boost/skills/`, recommended for
      `.ai/skills/`.
- [ ] Name doesn't collide with a reserved shipped-skill name
      unless override is intentional.
- [ ] `description` leads with the action, lists scenarios, has an
      `Activates:` trigger-phrase list, and is trimmed of articles
      and filler.
- [ ] Source lives in `.ai/skills/` (host-only) or
      `resources/boost/skills/` (shipped to consumers) — not in any
      generated per-agent dir.
- [ ] `vendor/bin/testbench package-boost:sync` ran clean.
- [ ] Generated files committed alongside the source.
