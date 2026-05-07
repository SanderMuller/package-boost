---
name: skill-authoring
description: "MUST USE when authoring an AI skill — creating a new SKILL.md, naming a skill, or deciding where one lives. Teaches namespacing to avoid silent collisions across host / vendor / package-boost shipped skills, frontmatter that actually triggers auto-activation, the `.ai/skills/` vs `resources/boost/skills/` source-dir choice, and the `package-boost:sync` regeneration step. Activates when: creating a skill, adding a skill, drafting a SKILL.md, naming a skill, choosing where a skill lives, editing any `.ai/skills/**/SKILL.md` or `resources/boost/skills/**/SKILL.md`, or user mentions: skill, SKILL.md, skill name, skill namespace, skill collision, vendor skill, shipped skill."
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
auto-activation. A vague one ("Helps with reviews") never matches; a
specific one matches reliably.

Required ingredients:

- **Lead with the action.** "Helps maintainers …", "Use when …",
  "Reviews …". One sentence.
- **Scenarios** the skill applies to.
- **Trigger phrases** after `Activates when:` — natural-language
  phrases a user would type. Include verbs (`writing`, `drafting`,
  `auditing`) and nouns the user would actually use.
- **Guardrail prefix.** For skills that must run before other work
  (collision-prevention, irreversible-edit safety), prefix with
  `MUST USE when …`.

Pattern:

```yaml
description: "Helps maintainers {do thing} for {scope}. {One sentence
on what's taught.} Activates when: {scenario 1}, {scenario 2}, or
user mentions {phrase 1}, {phrase 2}, {phrase 3}."
```

For a guardrail skill:

```yaml
description: "MUST USE when {trigger condition}. {What it prevents.}
Activates when: {file-path triggers}, {scenarios}, or user mentions
{phrases}."
```

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
- [ ] `description` leads with the action, lists scenarios, and has
      an `Activates when:` trigger-phrase list.
- [ ] Source lives in `.ai/skills/` (host-only) or
      `resources/boost/skills/` (shipped to consumers) — not in any
      generated per-agent dir.
- [ ] `vendor/bin/testbench package-boost:sync` ran clean.
- [ ] Generated files committed alongside the source.
