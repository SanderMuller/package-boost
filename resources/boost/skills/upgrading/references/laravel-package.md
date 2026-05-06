# Laravel Package Upgrade Guide Conventions

Apply on top of the generic guidance in `SKILL.md`. Distilled from a 2026 survey of spatie/laravel-medialibrary, spatie/laravel-permission, spatie/laravel-data, laravel/framework, filamentphp/filament.

## Filename convention (recommend `UPGRADING.md`, accept variants)

No single dominant convention exists. Surveyed packages use four locations:

| Package | Path |
|---|---|
| spatie/laravel-medialibrary | `UPGRADING.md` (root) |
| spatie/laravel-data | `UPGRADING.md` (root) |
| spatie/laravel-permission | `docs/upgrading.md` |
| laravel/framework | `upgrade.md` in `laravel/docs:<branch>` (separate repo, per-major branch) |
| filamentphp/filament | `packages/.../docs/14-upgrade-guide.md` (per-major branch, rendered to filamentphp.com) |

**Recommendation:** put `UPGRADING.md` at the package root unless you already have a docs site. The README must link to whichever location you use — release-notes' `## Breaking changes` bullets link via `[UPGRADING.md#anchor]`, so the path needs to resolve from the release body's render context (GitHub Releases page).

## Spatie pattern

Reverse-chronological H2 per major (`## From v10 to v11`, `## From v9 to v10`, …). Inside each:

- Prose-dominant ("rename X to Y") with full code blocks for non-trivial migrations.
- spatie/laravel-data uses adjacent code blocks with `// v3` / `// v4` comment labels — cleaner and recommended for new guides.
- spatie/laravel-permission has an "Upgrade Essentials" preamble H2 with universal pre-flight steps applied to all transitions. Smart pattern, worth adopting.

## Laravel framework pattern (`laravel/docs:<branch>/upgrade.md`)

- One H2 per single-major hop (`## Upgrading To 13.0 From 12.x`).
- "Estimated Upgrade Time: ~10 Minutes" callout near the top — useful for major framework bumps.
- **Top-of-file impact index**: `## High Impact Changes`, `## Medium Impact Changes`, `## Low Impact Changes` — anchor lists pointing into the body.
- Each H3 change carries a `Likelihood Of Impact: High|Medium|Low|Very Low` line.
- Adjacent code blocks labelled `// Laravel <= 12.x` and `// Laravel >= 13.x`.
- Mentions automated upgrade tooling: Laravel Shift, Laravel Boost (`/upgrade-laravel-v13` prompt).

If your guide runs over ~200 lines, adopt the impact-tagging pattern. Three of five surveyed packages use some form of it.

## Filament pattern (`docs/<n>-upgrade-guide.md`)

- One file per major branch (v3-to-v4 lives on the 4.x branch).
- Top-of-file sections: New requirements → Running the automated upgrade script → Publishing the configuration file → Breaking changes that must be handled manually.
- Breaking changes bucketed `High-impact (N)` / `Medium-impact (N)` / `Low-impact (N)`.
- Heavy MDX `<Aside>` admonitions (info / warning / tip) — translate to GitHub admonitions (`> [!NOTE]`, `> [!WARNING]`) if shipping plain markdown.
- **Automated upgrade script** is prominent — composer-distributed, with cross-platform caveats (Windows/PowerShell). If your package can ship one, mention it near the top.

## Composer constraint update

Every transition section should show the new `composer.json` constraint. Saves the reader from re-deriving it from the README.

```diff
"require": {
-    "vendor/package": "^4.0"
+    "vendor/package": "^5.0"
}
```

## Auto-discovery and `vendor:publish --force`

- Service provider auto-discovery has been default since Laravel 5.5. Don't tell users to re-register the SP after upgrade.
- `php artisan vendor:publish --tag="<short-name>-config" --force` is a common upgrade step — **warn that it overwrites local edits** to the published config. Recommend diffing first.
- `php artisan vendor:publish --tag="<short-name>-migrations"` is safe (additive); call out any new migrations that need running.

## Testbench version pinning

When a package bumps its `orchestra/testbench` requirement (e.g. `^9` → `^10`), downstream consumer test suites that share the same Testbench version may need a matching bump. Mention in the Requirements H3:

> Requires Orchestra Testbench `^10.0` (was `^9.0`). If your test suite pins Testbench, update accordingly.

## Stable H2 anchors are load-bearing

Release-notes' `## Breaking changes` bullets link to UPGRADING.md H2 anchors via `[UPGRADING.md#kebab-case-heading](UPGRADING.md#kebab-case-heading)`. GitHub generates anchors from headings.

**Do not rename H2s after release.** No automated check catches a renamed anchor — every release-notes link from prior releases breaks silently. If you must rename for clarity, add the new H2 alongside the old one with `<a id="old-anchor"></a>` HTML for back-compat.

## Anti-patterns specific to Laravel

| Anti-pattern | Why |
|---|---|
| "We changed X because Y" rationale | Belongs in release notes, not migration steps |
| Full-file dumps in before/after | Reader is migrating, not learning from scratch |
| Missing version-jump warning | "Upgrading from v3 to v5? do v3→v4 first" — surveyed packages don't ship this; you should |
| `--force` on vendor:publish without warning | Overwrites local config edits |
| Renaming H2s post-release | Breaks release-notes' breaking-change links from prior versions |
| Anchor names that don't match release-notes' link targets | The release-notes contract requires resolvable links |
| Mixing rolling deprecations into the major-transition section | Deprecations belong in release-notes / CHANGELOG until the breaking release lands |

## Cross-refs (Laravel-specific)

- `readme` skill's Laravel reference — README must link to UPGRADING.md.
- `release-notes` skill's Laravel reference — `## Breaking changes` bullets link here.
- `cross-version-laravel-support` skill — for code patterns that bridge multiple Laravel majors without breaking.
- `package-development` skill — Testbench harness and source layout.
