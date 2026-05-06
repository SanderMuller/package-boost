# Laravel Package Release Notes Conventions

Apply on top of the generic guidance in `SKILL.md`. Distilled from a 2026 survey of Spatie laravel-permission, Spatie laravel-data, laravel/horizon, awcodes/filament-curator, and barryvdh/laravel-debugbar release bodies.

## Survey reality (2026)

Patch and minor releases in well-maintained Laravel packages are **2–6 lines long**. They use GitHub's "Generate release notes" output verbatim: `## What's Changed` with PR-linked bullets, plus a `**Full Changelog**:` compare-link footer. Override with structure only when the change genuinely warrants it. Authors over-structuring patch releases looks busy, not professional.

## Highlight version-matrix shifts prominently

When a release changes the supported PHP or Laravel range, surface it. Readers scanning release notes for upgrade-readiness need this in the first 3 lines, not buried in a `What's Changed` bullet.

```
v6.0.0 — drops Laravel 10, requires PHP 8.2+

## Breaking changes
- Minimum Laravel bumped to ^11.0 (was ^10.0). See [UPGRADING.md#upgrading-from-v5-to-v6](UPGRADING.md#upgrading-from-v5-to-v6) for migration steps.
- Minimum PHP bumped to 8.2 (was 8.1). See [UPGRADING.md#upgrading-from-v5-to-v6](UPGRADING.md#upgrading-from-v5-to-v6) for migration steps.

## What's Changed
<auto-bullets>

**Full Changelog**: <compare-url>
```

Every bullet under `## Breaking changes` must end with a link to a matching UPGRADING.md anchor — see the `upgrading` skill for how to author the guide and its anchors.

## Ecosystem-plugin constraint shifts

For Filament, Livewire, or Nova plugins, a major bump in the host ecosystem is itself a breaking change in your plugin — even if your own API is unchanged. Call it out:

> **Breaking:** This release supports Filament v5. For Filament v4, stay on the 4.x line of this plugin.

Filament-Curator does this with a dedicated "Upgrading from v3 to v4" README section paired with the release body.

## Auto-discovery doesn't get release-noted

If your release adds or removes service-provider auto-discovery, **don't** mention auto-discovery itself — it's been default since Laravel 5.5. Mention only what the user-visible bindings/aliases change.

## Conventional commits in this ecosystem

Conventional commits are **rare** in Laravel package commit history. The survey found one of five releases (Filament-Curator) using `Chore(deps):` / `Fix:` prefixes inside bullets — and even there, no per-type subgrouping. Don't try to derive `### Added / ### Changed / ### Fixed / ### Removed` subsections from commit prefixes if the project doesn't use conventional commits consistently. The output looks structured but is artificial.

## CHANGELOG interplay

Two CHANGELOG strategies are common in Laravel packages — check `.github/workflows/` to see which the package uses, then follow the matching rule.

**Strategy A: hand-maintained CHANGELOG.** The maintainer edits `CHANGELOG.md` directly per release. Release body and CHANGELOG entry are written separately; the release body can be terser since CHANGELOG carries the canonical record.

**Strategy B: automated CHANGELOG.** A workflow file (commonly `.github/workflows/update-changelog.yml` running `stefanzweifel/changelog-updater-action`) prepends the GitHub release body to `CHANGELOG.md` on release publish. Detect this by grepping `.github/workflows/` for `changelog-updater-action` or similar. If present:

- The release body **becomes** the CHANGELOG entry verbatim — write it for both audiences.
- Don't reference internal context (Slack threads, issue-tracker labels) — CHANGELOG readers won't have it.
- Don't edit `CHANGELOG.md` by hand during release prep — the workflow will overwrite the section. Fix the release body instead.
- If a typo lands in the auto-prepended CHANGELOG entry after release, edit `CHANGELOG.md` directly with a follow-up commit (the workflow only prepends; it won't overwrite past entries).

## Anti-patterns specific to Laravel packages

| Anti-pattern | Why |
|---|---|
| Bumping the Laravel constraint without a release-notes mention | Users on the dropped major won't know why `composer update` fails |
| `### Added: Service provider` bullet | Auto-discovery is invisible plumbing, not a feature |
| Splitting one user-facing change across 3 PR bullets | Group narrative-level changes; let `## What's Changed` carry the granular PR list |
| "See PR #X" without describing the change | Forces reader to click through; the bullet exists to summarize |
| Long upgrade prose embedded in the release body | Move to UPGRADING.md (use the `upgrading` skill to write it), link from each `## Breaking changes` bullet via `[UPGRADING.md#anchor]` |

## Cross-refs (Laravel-specific)

- `readme` skill's Laravel reference — pair release-notes work with README version-table updates when the matrix changes.
- `lean-dist` skill — when a release changes `.gitattributes` and the dist tarball composition.
- `package-development` skill — when a release introduces Testbench-version constraints.
