---
name: readme
description: "Helps maintainers write or improve README files for any project. Teaches required sections, structure, voice, and a canonical staleness-audit pattern. Points to `references/laravel-package.md` for Laravel-package-specific conventions. Activates when: writing a README, improving an existing README, auditing a README for staleness, or user mentions readme, README.md, project documentation, package docs."
---

# README

Generic guidance for writing and auditing README files. Ecosystem-specific conventions (section ordering, version-matrix format, framework idioms) live in the `references/` subdirectory.

## Reference pointer

Working on a Laravel package? Also apply `references/laravel-package.md` from this skill directory — it covers Spatie/first-party section ordering, auto-discovery wording, Testbench testing snippet, and version-matrix conventions for ecosystem plugins.

Heuristic: it's a Laravel package if `composer.json`'s `require` lists `laravel/framework`, any `illuminate/*` (e.g. `illuminate/contracts`, `illuminate/support`), or any `filament/*` / `livewire/*` / `laravel/nova` ecosystem package. Pure-PHP libraries with none of those skip the reference and use generic guidance only.

## Pick a shape first

READMEs come in two shapes. Choose **before** writing:

- **Stub** (~30–50 lines). Defers all real docs to an external site. Used by `laravel/socialite`, `laravel/horizon`, and most first-party Laravel packages. Section sequence: Introduction → Official Documentation → Contributing → Code of Conduct → Security Vulnerabilities → License. Centred logo + badges row above the first H2. Pick this when a real docs site exists and is the canonical source.
- **Comprehensive** (~80–500 lines). Full docs in-README. Used by Spatie packages, ecosystem plugins, most community libraries. Pick this when there's no separate docs site, or when in-README is the docs site.

Don't mix shapes. A half-stubbed README that promises external docs but also dumps three feature sections inline frustrates readers.

## Required sections by shape

The required minimum differs between the two shapes — don't enforce comprehensive-shape sections on a stub README.

**Stub shape** (defers to external docs):
- **H1 title** + one-line tagline (≤ 12 words).
- **Introduction** — 1–3 sentences, what the project does.
- **Official Documentation** — link to the docs site (the reason to ship a stub).
- **Contributing** — link to CONTRIBUTING.md.
- **Security** — link to SECURITY.md or a security email.
- **License** — one-liner naming the SPDX identifier (e.g. "MIT"). The year + copyright holder belong in `LICENSE.md`, not the README.

Stubs intentionally **omit** Install / Basic usage / Testing — those live on the docs site. A stub that inlines them isn't a stub.

**Comprehensive shape** (full docs in-README):
- **H1 title** + one-line tagline (≤ 12 words).
- **Install** — exact, copy-pasteable install command (`composer require vendor/package` for PHP packages; the Laravel reference covers config/migration publish steps).
- **Basic usage** — at least one runnable code example. For Laravel packages, see Laravel reference.
- **Testing** — how to run the test suite (`composer test`, `vendor/bin/pest`, `vendor/bin/testbench package:test`).
- **License** — same as stub.

## Recommended sections

- **Badges row** — Packagist version, downloads, build status, license. Place above the first H2 in stubs, after the H1+tagline in comprehensive.
- **What it does** — 1–3 short feature bullets or a "What It Does" H2 before Install. Common in `spatie/laravel-permission`, `barryvdh/laravel-debugbar`. Helps readers triage in 5 seconds whether the package fits.
- **Code-first hero example** — a working snippet immediately under the H1, before any prose. Strong pattern in `spatie/laravel-medialibrary`, `spatie/laravel-data`, `spatie/laravel-permission`. Use when one-glance usage is feasible.
- **Configuration** — link to published config; document each key.
- **Upgrading** — link to UPGRADING.md (use the `upgrading` skill to write it). The release-notes contract requires this link to resolve.
- **Changelog** — link to `CHANGELOG.md` and the GitHub releases page.
- **Contributing** — link to CONTRIBUTING.md.
- **Security** — link to SECURITY.md or a security email.
- **Credits** — author + contributors.

## Closing matter

After the substantive sections, every Laravel-ecosystem README closes with the same set of H2s — **Testing, Changelog, Contributing, Security, Credits, License** — though exact ordering varies. Spatie packages often interleave Postcardware between Security and Credits, and Alternatives before License. Wire-elements packages append a "Beautiful components crafted with Livewire" promo block after License. Adopt the spirit (these all appear at the end, License is dead-last) rather than rigid ordering.

Don't put substantive docs after License. Once a reader hits License, scrolling stops.

## Voice & structure

- One H1 only.
- Active voice, present tense.
- Code-first: show, then tell. A working snippet beats a paragraph of explanation.
- Short paragraphs; bullets and tables for scannability.
- Don't inline-novella. If a section runs past ~30 lines, link to deeper docs.
- GitHub admonitions (`> [!NOTE]`, `> [!WARNING]`, `> [!IMPORTANT]`) for security/perf caveats and upgrade notes. Used heavily in `awcodes/filament-curator` and `barryvdh/laravel-debugbar`.

## Audit pattern (canonical)

Run this when reviewing an existing README — at PR time, pre-release, or when something feels off.

1. Diff against recent commits: `git log <last-tag>..HEAD --oneline`.
2. For each commit touching the **public API**, search the README for the changed name and update or remove stale references. Public API in a Laravel package: facade methods, console-command signatures, config keys, contract interfaces, traits/scopes, public class methods, event class names.
3. Run every code block in the Install + Basic usage sections. Anything that errors or no longer compiles is a blocker.
4. Check config keys mentioned in the README against the actual published config file. Removed keys must be removed from prose.
5. Check version constraints (`requires PHP X.Y`, `Laravel A-B`). Verify against `composer.json`.
6. Check links — broken external links and dead anchors are the most common rot.
7. If a feature was removed in this release window, scan the README for any mention of it and excise.

Pass criteria: no stale claims, all install + usage code blocks run, no broken links.

## Common mistakes

| Mistake | Why it's bad |
|---|---|
| "TODO" / "Coming soon" sections shipped | Signals abandonment; remove until ready |
| Multiple H1s | Breaks anchor links and TOCs |
| Code blocks without language tag | No syntax highlighting |
| Stale install command after vendor rename | Breaks every new user |
| Version matrix table that doesn't match `composer.json` | Worse than no matrix |
| Inline screenshots without alt text | Inaccessible |
| Duplicate badges (one row top, one row bottom) | Visual noise |

## Cross-refs

- `release-notes` skill — when you ship changes that need a release body.
- `lean-dist` skill — `.gitattributes` `export-ignore` keeps `.ai/`, `.claude/`, etc. out of `composer archive`.
- `package-development` skill — Laravel package conventions, Testbench harness.
