---
name: lean-dist
description: "Use when prepping a package release, when `.gitattributes` is missing or thin, or to keep `composer archive` / Packagist `--prefer-dist` tarballs lean. Activates on: lean dist, gitattributes, export-ignore, dist size, packagist tarball, composer archive bloat, lean-package-validator, stolt lpv, AI files in dist, .ai shipping to dist, .claude in tarball, CLAUDE.md in tarball, AGENTS.md in tarball."
---

# Lean Dist

`.gitattributes` `export-ignore` controls what lands in
`composer require --prefer-dist` tarballs and GitHub release archives.
Dev-only paths and AI-tooling artifacts have no business in a
consumer's `vendor/` tree — they bloat installs and occasionally leak
working-copy context.

This skill is the **on-ramp** to `stolt/lean-package-validator` (lpv).
Once installed, lpv's three shipped skills
(`creating-gitattributes-file`, `updating-gitattributes-file`,
`validating-gitattributes-file`) take over the per-command work. This
skill covers only what those don't: install caveats, AI-era gaps,
commit-ordering, and OSS-norm review.

## When to activate

- Cutting a release and `composer archive HEAD` shows files that
  shouldn't ship.
- A `.gitattributes` is missing, or hasn't been touched since the
  repo grew an `.ai/` / `.claude/` / `AGENTS.md` / `CLAUDE.md`.
- Auditing a package after switching to AI-assisted development.

## Step 1 — Install lpv

```bash
composer require --dev stolt/lean-package-validator
```

Pin `^5.7.1` if your composer cache resolves to v5.7.0 — that
release had a Symfony 8 / Laravel 13 install break, fixed in 5.7.1.
`composer clear-cache && composer require --dev stolt/lean-package-validator`
forces fresh Packagist metadata.

After install, `package-boost:sync` discovers lpv's
`resources/boost/skills/` directory automatically (vendor-skill
discovery, 0.9.0+). The three lpv skills surface in
`.claude/skills/` and `.github/skills/` on the next sync — hand off
all command-level work to them.

## Step 2 — Audit, then create or update

Run lpv's audit:

```bash
vendor/bin/lean-package-validator validate
```

Then hand off:

- **No `.gitattributes`** → lpv's `creating-gitattributes-file` skill.
- **Drift on existing file** → lpv's `updating-gitattributes-file`
  skill. Always `validate --diff` before any overwrite.

> **lpv's preset is opinionated.** v5.7.0 ignores `LICENSE.md`,
> `README.md`, `CHANGELOG.md`, and `SECURITY.md` by default — most
> OSS maintainers want those in the dist. Review the generated file
> before committing; remove lines you disagree with.

## Step 3 — Add AI-era export-ignore lines

lpv's preset (v5.7.1) already covers `.ai/`, `.claude/`, `.cursor/`,
`.junie/`, `.agents/`, and `.kiro/`. The gaps it does **not** know
about:

```
AGENTS.md           export-ignore
CLAUDE.md           export-ignore
GEMINI.md           export-ignore
.cache/             export-ignore
.cursorrules        export-ignore
.windsurfrules      export-ignore
```

Append directly to `.gitattributes` — do **not** use a `.lpv` file,
which overrides rather than extends lpv's preset.

> **Verify against your installed lpv version** before relying on
> this list. lpv's preset evolves: v5.6.1 picked up `.ai/` and
> `.claude/`; v5.7.1 added `.cursor/`, `.junie/`, `.agents/`,
> `.kiro/`. Run `lean-package-validator validate` after `create`
> and trust the `expected_gitattributes_content` diff over this
> list — preset growth means today's gap may be tomorrow's default.

> **Re-running `lpv update` after this step may strip these manual
> lines.** `update` regenerates from preset. Treat the appended
> block as the source of truth: commit `.gitattributes` and use
> `validate` (read-only) in CI rather than re-running `update`.

## Step 4 — Commit, *then* validate the archive

Critical ordering: `git archive` reads `.gitattributes` from HEAD,
not the working tree. An uncommitted `.gitattributes` is invisible
to the archive — `validate --validate-git-archive` will report
every preset entry as `unexpected_artifacts` until you commit.

```bash
git add .gitattributes
git commit -m "Add lean .gitattributes"
vendor/bin/lean-package-validator validate --validate-git-archive
```

Exit 1 on bloat, exit 0 on lean. Output lists each leaked path under
`unexpected_artifacts`, so the failure is actionable.

## Step 5 — CI

Hand off to lpv's `validating-gitattributes-file` skill for the
composer-script and GitHub Action snippets. The gate command is
`lean-package-validator validate --validate-git-archive` — exit 1
on bloat, exit 0 on lean.

## Output format note

lpv v5.6.1+ auto-detects agentic invocation and emits **JSON instead
of text** when triggered through a Claude Code skill. Shape:
`{ "command", "status", "message", ... }` with category-specific
fields like `unexpected_artifacts` (array). Parse the JSON when
chaining steps; don't assume the human-readable format.

## Edge cases

- **Testing-helper packages** — `*/testing`, orchestra-style
  harnesses, and Pest plugins legitimately ship `tests/` so
  consumers can extend the base test case. lpv's preset wants
  `tests/` ignored; remove that line from the generated
  `.gitattributes` before committing, then re-validate.
- **Monorepos** — run lpv per leaf package, not at repo root.
  Each package has its own `composer.json` and its own dist; root
  `.gitattributes` doesn't propagate into split tags.
- **Hand-rolled `.gitattributes`** — `validate --diff` first.
  Never blanket-`update` a curated file.

